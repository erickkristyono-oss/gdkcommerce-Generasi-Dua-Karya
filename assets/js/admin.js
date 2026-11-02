/* =========================================================
   GDK Commerce — Panel Admin
   Ringkasan, Pesanan, Refund, Produk & Harga, Pengguna, Supplier
   ========================================================= */
(function () {
  "use strict";

  document.addEventListener("gdk:ready", (e) => {
    const G = window.GDK;
    const { api, toast, esc, rupiah, icons: I, $, $$ } = G;
    const fmtDate = G.fmtDate;
    const user = e.detail.user;
    const view = $("#adminView");

    if (!user) { location.replace("login.html?next=admin.html"); return; }
    if (user.role !== "admin") {
      $("#adminTabs").hidden = true; $("#adminTitle").textContent = "Akses Ditolak";
      view.innerHTML = `<div class="panel denied"><h2>Halaman khusus admin</h2><p style="color:var(--muted)">Akun Anda tidak memiliki akses ke panel admin.</p><a href="index.html" class="btn-outline">Kembali ke Beranda</a></div>`;
      return;
    }

    /* ---------- Util ---------- */
    const num = (n) => Number(n || 0).toLocaleString("id-ID");
    const fmtDay = (iso) => new Date(iso + "T00:00:00").toLocaleDateString("id-ID", { day: "2-digit", month: "short" });
    const STATUS = {
      unpaid: ["Menunggu Bayar", "wait"], processing: ["Diproses", "wait"], success: ["Berhasil", "ok"],
      failed: ["Gagal", "bad"], expired: ["Kedaluwarsa", "muted"]
    };
    const statusBadge = (o) => {
      const [label, cls] = STATUS[o.status] || [o.status, "muted"];
      let extra = "";
      if (o.status === "failed" && o.refund_status === "pending") extra = ` <span class="status bad">Perlu Refund</span>`;
      if (o.refund_status === "done") extra = ` <span class="status muted">${o.refund_method === "saldo" ? "Refund ke Saldo" : "Refund Manual"}</span>`;
      if (o.retry_count) extra += ` <span class="status muted" title="Dikirim ulang">↻${o.retry_count}</span>`;
      return `<span class="status ${cls}">${label}</span>${extra}`;
    };
    const debounce = (fn, ms = 350) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const isoDay = (d) => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
    const pager = (d, onGo) => {
      const pages = Math.max(1, Math.ceil(d.total / d.per));
      const el = document.createElement("div");
      el.className = "pager";
      el.innerHTML = `<span>${num(d.total)} data · halaman ${d.page} dari ${pages}</span><div>
        <button class="btn-outline btn-sm" data-go="${d.page - 1}" ${d.page <= 1 ? "disabled" : ""}>‹ Sebelumnya</button>
        <button class="btn-outline btn-sm" data-go="${d.page + 1}" ${d.page >= pages ? "disabled" : ""}>Berikutnya ›</button></div>`;
      el.addEventListener("click", (ev) => { const b = ev.target.closest("[data-go]"); if (b && !b.disabled) onGo(+b.dataset.go); });
      return el;
    };
    const loading = (el) => { el.style.opacity = ".55"; el.style.pointerEvents = "none"; };
    const loaded = (el) => { el.style.opacity = ""; el.style.pointerEvents = ""; };

    /* ---------- Modal ---------- */
    let modal = null;
    function openModal(html, wide = false) {
      if (!modal) {
        modal = document.createElement("div");
        modal.className = "modal"; modal.setAttribute("role", "dialog"); modal.setAttribute("aria-modal", "true");
        modal.innerHTML = `<div class="modal-card"></div>`;
        document.body.appendChild(modal);
        modal.addEventListener("click", (ev) => { if (ev.target === modal || ev.target.closest("[data-close]")) closeModal(); });
        document.addEventListener("keydown", (ev) => { if (ev.key === "Escape" && modal.classList.contains("open")) closeModal(); });
      }
      const card = $(".modal-card", modal);
      card.classList.toggle("wide", wide);
      card.innerHTML = html;
      modal.classList.add("open");
      document.body.style.overflow = "hidden";
      return card;
    }
    function closeModal() { if (modal) { modal.classList.remove("open"); document.body.style.overflow = ""; } }
    const modalHead = (title, sub = "") => `<div class="modal-head"><div><h3>${title}</h3>${sub ? `<small>${sub}</small>` : ""}</div><button class="modal-close" data-close aria-label="Tutup">${I.close}</button></div>`;

    /** Dialog konfirmasi dengan input opsional. fields: [{name,label,placeholder,required,type}] */
    function ask({ title, message, fields = [], confirm = "Simpan", danger = false }) {
      return new Promise((resolve) => {
        const card = openModal(`${modalHead(esc(title))}
          ${message ? `<p style="margin:0 0 16px;color:var(--ink-2)">${message}</p>` : ""}
          <form id="askForm" novalidate>
            ${fields.map((f) => `<div class="field"><label for="ask_${f.name}">${esc(f.label)}</label>
              ${f.type === "textarea" ? `<textarea id="ask_${f.name}" name="${f.name}" placeholder="${esc(f.placeholder || "")}" style="min-height:80px">${esc(f.value || "")}</textarea>`
                : `<input id="ask_${f.name}" name="${f.name}" type="${f.type || "text"}" placeholder="${esc(f.placeholder || "")}" value="${esc(f.value ?? "")}">`}
              <span class="err">${esc(f.error || "Wajib diisi")}</span></div>`).join("")}
            <div class="actions-row" style="justify-content:flex-end">
              <button type="button" class="btn-outline btn-sm" data-close>Batal</button>
              <button type="submit" class="btn-outline btn-sm ${danger ? "btn-danger" : "btn-success"}">${esc(confirm)}</button>
            </div>
          </form>`);
        let done = false;
        const finish = (v) => { if (done) return; done = true; obs.disconnect(); resolve(v); };
        const obs = new MutationObserver(() => { if (!modal.classList.contains("open")) finish(null); });
        obs.observe(modal, { attributes: true, attributeFilter: ["class"] });
        const form = $("#askForm", card);
        const first = form.querySelector("input,textarea"); if (first) setTimeout(() => first.focus(), 50);
        form.addEventListener("submit", (ev) => {
          ev.preventDefault();
          const out = {}; let ok = true;
          fields.forEach((f) => {
            const el = form.elements[f.name]; const v = el.value.trim(); out[f.name] = v;
            const bad = f.required && v.length < (f.min || 1);
            el.closest(".field").classList.toggle("invalid", bad); if (bad) ok = false;
          });
          if (!ok) return;
          finish(out);
        });
      });
    }

    /* ---------- Header: saldo supplier & badge refund ---------- */
    function setSupplierChip(s) {
      const chip = $("#supplierChip");
      if (!s) { chip.hidden = true; return; }
      chip.hidden = false;
      const mode = s.driver === "mock" ? "Simulasi (mock)" : s.testing ? "Digiflazz · testing" : "Digiflazz · produksi";
      const dot = s.balance === null ? "bad" : s.driver === "mock" || s.testing ? "warn" : "";
      chip.innerHTML = `<span class="dot ${dot}"></span><span><small style="color:var(--muted);display:block;font-size:12px">${esc(mode)}</small>${s.balance === null ? `<b style="font-size:14px;color:#b42318">Saldo tidak terbaca</b>` : `<b>${rupiah(s.balance)}</b>`}</span>`;
      chip.title = s.error || "Saldo deposit supplier";
    }
    function setDigitalCount(n) {
      const el = $("#digitalCount"); if (!el) return;
      el.hidden = !n; el.textContent = n > 99 ? "99+" : n; el.title = n + " pesanan digital menunggu dikirim";
    }
    function setRefundCount(n) {
      const el = $("#refundCount");
      el.hidden = !n; el.textContent = n > 99 ? "99+" : n;
    }

    /* =========================================================
       TAB: RINGKASAN
       ========================================================= */
    const METRICS = { omzet: { label: "Omzet", fmt: rupiah }, laba: { label: "Laba kotor", fmt: rupiah }, count: { label: "Transaksi", fmt: num } };
    let chartMetric = "omzet";

    async function tabRingkasan() {
      view.innerHTML = `<div class="empty">Memuat ringkasan…</div>`;
      const d = await api("admin/dashboard.php");
      setSupplierChip(d.supplier); setRefundCount(d.alerts.refund_pending); setDigitalCount(d.alerts.digital_waiting);
      const R = d.ranges;
      const supplierTile = d.supplier.balance === null
        ? `<b style="font-size:18px;color:#b42318">Tidak terbaca</b><span>${esc(d.supplier.error || "")}</span>`
        : `<b>${rupiah(d.supplier.balance)}</b><span>${d.supplier.driver === "mock" ? "Mode simulasi — bukan saldo asli" : d.supplier.testing ? "Mode testing Digiflazz" : "Deposit Digiflazz"}</span>`;

      const alerts = [];
      alerts.push(d.alerts.refund_pending
        ? `<button class="alert bad" data-goto="refund"><span class="ic">${I.receipt}</span><span><b>${d.alerts.refund_pending} pesanan perlu refund</b><br>Total ${rupiah(d.alerts.refund_amount)}</span></button>`
        : `<div class="alert ok"><span class="ic">${I.check}</span><span><b>Tidak ada refund tertunda</b></span></div>`);
      alerts.push(d.alerts.stuck
        ? `<button class="alert warn" data-goto="pesanan" data-status="processing"><span class="ic">${I.clock}</span><span><b>${d.alerts.stuck} pesanan diproses &gt; 30 menit</b><br>Cek ulang ke supplier</span></button>`
        : `<div class="alert ok"><span class="ic">${I.check}</span><span><b>${d.alerts.processing} pesanan sedang diproses</b><br>Tidak ada yang macet</span></div>`);
      alerts.push(d.alerts.unavailable
        ? `<button class="alert warn" data-goto="produk" data-state="unavailable"><span class="ic">${I.tag}</span><span><b>${d.alerts.unavailable} produk gangguan</b><br>Tidak tersedia di supplier</span></button>`
        : `<div class="alert ok"><span class="ic">${I.check}</span><span><b>Semua produk aktif tersedia</b><br>${d.alerts.unpaid} pesanan menunggu bayar</span></div>`);

      if (d.alerts.digital_waiting) alerts.unshift(`<button class="alert warn" data-goto="pesanan" data-status="digital_waiting"><span class="ic">${I.download}</span><span><b>${d.alerts.digital_waiting} produk digital menunggu dikirim</b><br>Buka pesanan lalu klik Kirim Produk</span></button>`);
      const statusRows = [["success", "Berhasil"], ["processing", "Diproses"], ["unpaid", "Menunggu Bayar"], ["failed", "Gagal"], ["expired", "Kedaluwarsa"]]
        .map(([k, l]) => `<div><span>${l}</span><b>${num(d.status[k] || 0)}</b></div>`).join("");

      view.innerHTML = `
        <div class="tiles">
          <div class="tile"><small>Omzet hari ini</small><b>${rupiah(R.today.omzet)}</b><span>7 hari <em>${rupiah(R.d7.omzet)}</em> · 30 hari <em>${rupiah(R.d30.omzet)}</em></span></div>
          <div class="tile"><small>Laba kotor hari ini</small><b>${rupiah(R.today.laba)}</b><span>7 hari <em>${rupiah(R.d7.laba)}</em> · 30 hari <em>${rupiah(R.d30.laba)}</em></span></div>
          <div class="tile"><small>Transaksi berhasil hari ini</small><b>${num(R.today.count)}</b><span>7 hari <em>${num(R.d7.count)}</em> · 30 hari <em>${num(R.d30.count)}</em></span></div>
          <div class="tile"><small>Saldo supplier</small>${supplierTile}</div>
        </div>
        <div class="alerts">${alerts.join("")}</div>
        <div class="grid-2">
          <section class="panel">
            <div class="card-head">
              <h2>Tren 14 hari terakhir</h2>
              <div class="seg" id="metricSeg">${Object.entries(METRICS).map(([k, m]) => `<button data-m="${k}" class="${k === chartMetric ? "active" : ""}">${m.label}</button>`).join("")}</div>
            </div>
            <div class="chart" id="chart"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
              <small style="color:var(--muted)">Pesanan berhasil, per tanggal selesai. Laba kotor = harga jual − harga modal.</small>
              <button class="linkish" id="toggleTable">Lihat tabel</button>
            </div>
            <div id="chartTable" hidden></div>
          </section>
          <section class="panel">
            <h2>Status pesanan · 30 hari</h2>
            <div class="status-list">${statusRows}</div>
            <p style="color:var(--muted);font-size:13px;margin:14px 0 0">Pengguna: <b style="color:var(--ink)">${num(d.users.total)}</b> · baru 7 hari: <b style="color:var(--ink)">${num(d.users.new7)}</b></p>
            <p style="color:var(--muted);font-size:13px;margin:6px 0 0" title="Total Saldo GDK milik pelanggan — dana yang wajib Anda sediakan">Total Saldo GDK pelanggan: <b style="color:var(--ink)">${rupiah(d.alerts.wallet_total)}</b> · refund otomatis 7 hari: <b style="color:var(--ink)">${num(d.alerts.auto_refunded7)}</b></p>
          </section>
        </div>
        <section class="panel" style="margin-top:14px">
          <h2>Produk terlaris · 30 hari</h2>
          ${d.top.length ? `<div class="table-wrap"><table class="tx"><thead><tr><th>Produk</th><th class="num">Transaksi</th><th class="num">Omzet</th><th class="num">Laba kotor</th></tr></thead>
            <tbody>${d.top.map((t) => `<tr><td>${esc(t.brand)}</td><td class="num">${num(t.count)}</td><td class="num">${rupiah(t.omzet)}</td><td class="num">${rupiah(t.laba)}</td></tr>`).join("")}</tbody></table></div>`
            : `<div class="empty" style="padding:24px">Belum ada transaksi berhasil.</div>`}
        </section>`;

      const drawAll = () => {
        drawChart($("#chart"), d.series, chartMetric);
        $("#chartTable").innerHTML = `<div class="table-wrap" style="margin-top:12px"><table class="tx"><thead><tr><th>Tanggal</th><th class="num">Transaksi</th><th class="num">Omzet</th><th class="num">Laba kotor</th></tr></thead>
          <tbody>${d.series.slice().reverse().map((s) => `<tr><td>${fmtDay(s.date)}</td><td class="num">${num(s.count)}</td><td class="num">${rupiah(s.omzet)}</td><td class="num">${rupiah(s.laba)}</td></tr>`).join("")}</tbody></table></div>`;
      };
      drawAll();
      $("#metricSeg").addEventListener("click", (ev) => {
        const b = ev.target.closest("[data-m]"); if (!b) return;
        chartMetric = b.dataset.m; $$("#metricSeg button").forEach((x) => x.classList.toggle("active", x === b));
        drawChart($("#chart"), d.series, chartMetric);
      });
      $("#toggleTable").addEventListener("click", (ev) => {
        const t = $("#chartTable"); t.hidden = !t.hidden; ev.target.textContent = t.hidden ? "Lihat tabel" : "Sembunyikan tabel";
      });
      $$("[data-goto]", view).forEach((b) => b.addEventListener("click", () => {
        if (b.dataset.status) ordersState.status = b.dataset.status;
        if (b.dataset.state) productsState.state = b.dataset.state;
        go(b.dataset.goto);
      }));
      window.addEventListener("resize", debounce(() => { if (current === "ringkasan" && $("#chart")) drawChart($("#chart"), d.series, chartMetric); }, 200), { once: true });
    }

    /** Bar chart satu seri dengan tooltip per batang. */
    function drawChart(el, series, metric) {
      const M = METRICS[metric];
      const W = Math.max(320, el.clientWidth || 640), H = 240;
      const pad = { t: 12, r: 8, b: 26, l: 8 };
      const vals = series.map((s) => s[metric]);
      const max = Math.max(...vals, 0);
      // Skala "rapi"
      const nice = (v) => { if (v <= 0) return metric === "count" ? 4 : 100000; const p = Math.pow(10, Math.floor(Math.log10(v))); const f = v / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p; };
      const top = nice(max);
      const ticks = 4;
      const short = (v) => metric === "count" ? num(v) : v >= 1e6 ? (v / 1e6).toLocaleString("id-ID", { maximumFractionDigits: 1 }) + " jt" : v >= 1e3 ? (v / 1e3).toLocaleString("id-ID", { maximumFractionDigits: 0 }) + " rb" : num(v);
      // Lebar label sumbu-y
      pad.l = Math.max(...Array.from({ length: ticks + 1 }, (_, i) => short(top * i / ticks).length)) * 6.6 + 10;
      const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
      const band = iw / series.length;
      const bw = Math.max(6, Math.min(36, band - 6)); // celah ≥ 2px antarbatang
      const y = (v) => pad.t + ih - (v / top) * ih;
      const step = W < 520 ? 3 : 2;

      let g = `<g class="grid">`;
      for (let i = 0; i <= ticks; i++) { const v = top * i / ticks; g += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(v)}" y2="${y(v)}"/>`; }
      g += `</g><g class="axis">`;
      for (let i = 0; i <= ticks; i++) { const v = top * i / ticks; g += `<text x="${pad.l - 8}" y="${y(v) + 4}" text-anchor="end">${short(v)}</text>`; }
      series.forEach((s, i) => { if ((series.length - 1 - i) % step === 0) g += `<text x="${pad.l + band * i + band / 2}" y="${H - 6}" text-anchor="middle">${fmtDay(s.date)}</text>`; });
      g += `</g>`;

      let bars = "";
      series.forEach((s, i) => {
        const v = s[metric]; const x = pad.l + band * i + (band - bw) / 2; const yt = y(v); const h = pad.t + ih - yt;
        const r = Math.min(4, bw / 2, h);
        const path = h <= 0 ? "" : `M${x},${pad.t + ih} V${yt + r} Q${x},${yt} ${x + r},${yt} H${x + bw - r} Q${x + bw},${yt} ${x + bw},${yt + r} V${pad.t + ih} Z`;
        bars += `<g class="col" tabindex="0" data-i="${i}" role="img" aria-label="${fmtDay(s.date)}: ${M.fmt(v)}">
          <rect class="hit" x="${pad.l + band * i}" y="${pad.t}" width="${band}" height="${ih}" rx="6"/>
          ${path ? `<path class="bar" d="${path}"/>` : ""}</g>`;
      });

      el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="group" aria-label="${M.label} 14 hari terakhir">${g}${bars}</svg><div class="chart-tip" aria-hidden="true"></div>
        ${max === 0 ? `<div class="chart-empty">Belum ada transaksi berhasil dalam 14 hari terakhir.</div>` : ""}`;
      const tip = $(".chart-tip", el); const svg = $("svg", el);
      const show = (g) => {
        const s = series[+g.dataset.i];
        tip.replaceChildren();
        const b = document.createElement("b"); b.textContent = M.fmt(s[metric]);
        const small = document.createElement("small"); const key = document.createElement("span"); key.className = "key";
        small.append(key, document.createTextNode(M.label + " · " + new Date(s.date + "T00:00:00").toLocaleDateString("id-ID", { weekday: "short", day: "numeric", month: "short" })));
        const extra = document.createElement("small"); extra.style.display = "block";
        extra.textContent = metric === "count" ? rupiah(s.omzet) + " omzet" : num(s.count) + " transaksi";
        tip.append(b, small, extra);
        const scale = svg.getBoundingClientRect().width / W;
        const i = +g.dataset.i;
        const yt = Math.min(y(s[metric]), pad.t + ih - 4);
        tip.style.left = Math.min(Math.max((pad.l + band * i + band / 2) * scale, 70), el.clientWidth - 70) + "px";
        tip.style.top = yt * scale + "px";
        tip.classList.add("show");
      };
      $$(".col", el).forEach((g) => {
        g.addEventListener("pointerenter", () => show(g)); g.addEventListener("focus", () => show(g));
        g.addEventListener("pointerleave", () => tip.classList.remove("show")); g.addEventListener("blur", () => tip.classList.remove("show"));
      });
    }

    /* =========================================================
       TAB: PESANAN & REFUND
       ========================================================= */
    const ordersState = { status: "", q: "", from: "", to: "", page: 1, preset: "" };

    async function tabPesanan(refundMode = false) {
      const st = refundMode ? { status: "refund", q: "", from: "", to: "", page: 1 } : ordersState;
      view.innerHTML = `
        ${refundMode ? `<div class="note">Pesanan gagal biasanya <b>otomatis direfund ke Saldo GDK</b>. Yang muncul di sini adalah pesanan yang belum terefund (mis. refund otomatis dimatikan, atau pesanan lama). Pilih <b>Refund ke Saldo</b>, atau transfer manual lalu klik <b>Refund Manual</b>. Bisa juga dicoba <b>Kirim Ulang</b> dari detail pesanan.</div>` : ""}
        <div class="filters" id="oFilters">
          ${refundMode ? "" : `<select id="fStatus" aria-label="Status">
            ${[["", "Semua status"], ["digital_waiting", "Digital: menunggu dikirim"], ["unpaid", "Menunggu Bayar"], ["processing", "Diproses"], ["success", "Berhasil"], ["failed", "Gagal"], ["refund", "Perlu Refund"], ["refunded", "Sudah Refund"], ["expired", "Kedaluwarsa"]]
              .map(([v, l]) => `<option value="${v}" ${st.status === v ? "selected" : ""}>${l}</option>`).join("")}</select>
          <span class="chips" id="fPreset">${[["today", "Hari ini"], ["7", "7 hari"], ["30", "30 hari"]].map(([v, l]) => `<button class="chip ${st.preset === v ? "active" : ""}" data-p="${v}">${l}</button>`).join("")}</span>
          <input type="date" id="fFrom" value="${esc(st.from)}" aria-label="Dari tanggal">
          <input type="date" id="fTo" value="${esc(st.to)}" aria-label="Sampai tanggal">`}
          <input class="grow" id="fQ" type="search" placeholder="Cari invoice, nomor tujuan, email, nama, produk…" value="${esc(st.q)}">
        </div>
        <section class="panel" id="oPanel"><div class="empty">Memuat pesanan…</div></section>`;

      const panel = $("#oPanel");
      const load = async () => {
        loading(panel);
        const qs = new URLSearchParams({ status: st.status, q: st.q, from: st.from, to: st.to, page: st.page, per: 25 });
        let d;
        try { d = await api("admin/orders.php?" + qs); } catch (err) { panel.innerHTML = `<div class="empty">${esc(err.message)}</div>`; loaded(panel); return; }
        loaded(panel);
        if (refundMode) setRefundCount(d.total);
        const sumLine = refundMode
          ? `<b>${num(d.total)}</b> pesanan menunggu refund`
          : `<b>${num(d.total)}</b> pesanan${d.success_sum ? ` · omzet berhasil <b>${rupiah(d.success_sum)}</b>` : ""}`;
        panel.innerHTML = `<div class="card-head"><span style="color:var(--ink-2);font-size:14px">${sumLine}</span>
            <button class="btn-outline btn-sm" id="oRefresh">Muat ulang</button></div>
          ${d.orders.length ? `<div class="table-wrap"><table class="tx admin"><thead><tr>
            <th>Invoice</th><th>Tanggal</th><th>Pelanggan</th><th>Produk · Tujuan</th><th class="num">Total</th><th class="num">Laba</th><th>Status</th>${refundMode ? "<th></th>" : ""}</tr></thead>
            <tbody>${d.orders.map((o) => `<tr class="click" data-inv="${esc(o.invoice)}">
              <td><b>${esc(o.invoice)}</b><br><small>${esc(o.payment.name || "-")}</small></td>
              <td>${fmtDate(o.created_at)}</td>
              <td>${esc(o.user.name)}<br><small>${esc(o.user.email)}</small></td>
              <td>${esc(o.brand)} · ${esc(o.item)}<br><small>${esc(o.customer_no)}</small></td>
              <td class="num"><b>${rupiah(o.total)}</b></td>
              <td class="num">${o.margin === null ? "<small>-</small>" : `<span class="${o.margin < 0 ? "neg" : ""}">${rupiah(o.margin)}</span>`}</td>
              <td>${statusBadge(o)}${o.admin_note ? `<br><small title="${esc(o.admin_note)}">📝 ${esc(o.admin_note.slice(0, 28))}${o.admin_note.length > 28 ? "…" : ""}</small>` : ""}</td>
              ${refundMode ? `<td><div class="actions-row" style="flex-wrap:nowrap"><button class="btn-outline btn-sm btn-success" data-refund="${esc(o.invoice)}" data-how="refund_balance">Refund ke Saldo</button><button class="btn-outline btn-sm" data-refund="${esc(o.invoice)}" data-how="refund_done">Manual</button></div></td>` : ""}
            </tr>`).join("")}</tbody></table></div>`
          : `<div class="empty" style="padding:30px">${refundMode ? "Tidak ada refund tertunda. 🎉" : "Tidak ada pesanan yang cocok dengan filter."}</div>`}`;
        if (d.orders.length) panel.appendChild(pager(d, (p) => { st.page = p; load(); }));
        $("#oRefresh", panel).addEventListener("click", load);
        $$("tr.click", panel).forEach((tr) => tr.addEventListener("click", (ev) => {
          if (ev.target.closest("[data-refund]")) return;
          orderDetail(tr.dataset.inv, load);
        }));
        $$("[data-refund]", panel).forEach((b) => b.addEventListener("click", () => doAction(b.dataset.refund, b.dataset.how, load)));
      };

      const fQ = $("#fQ");
      fQ.addEventListener("input", debounce(() => { st.q = fQ.value.trim(); st.page = 1; load(); }));
      if (!refundMode) {
        $("#fStatus").addEventListener("change", (ev) => { st.status = ev.target.value; st.page = 1; load(); });
        const setDates = () => { $("#fFrom").value = st.from; $("#fTo").value = st.to; $$("#fPreset .chip").forEach((c) => c.classList.toggle("active", c.dataset.p === st.preset)); };
        $("#fPreset").addEventListener("click", (ev) => {
          const b = ev.target.closest("[data-p]"); if (!b) return;
          if (st.preset === b.dataset.p) { st.preset = ""; st.from = ""; st.to = ""; }
          else {
            st.preset = b.dataset.p; const now = new Date(); st.to = isoDay(now);
            const from = new Date(now); if (b.dataset.p !== "today") from.setDate(from.getDate() - (+b.dataset.p - 1));
            st.from = isoDay(from);
          }
          setDates(); st.page = 1; load();
        });
        ["fFrom", "fTo"].forEach((id) => $("#" + id).addEventListener("change", () => { st.from = $("#fFrom").value; st.to = $("#fTo").value; st.preset = ""; setDates(); st.page = 1; load(); }));
      }
      load();
    }

    const ACTION_DIALOG = {
      refund_balance: { title: "Refund ke Saldo GDK", message: "Total pembayaran pesanan ini akan langsung ditambahkan ke Saldo GDK pelanggan dan bisa dipakai untuk transaksi berikutnya.", confirm: "Refund ke Saldo",
        fields: [{ name: "note", label: "Catatan (opsional)", placeholder: "mis. Produk gangguan" }] },
      refund_done: { title: "Refund Manual (Transfer)", message: "Gunakan bila dana sudah Anda transfer langsung ke rekening/e-wallet pelanggan.", confirm: "Refund Selesai",
        fields: [{ name: "note", label: "Keterangan refund", placeholder: "mis. Transfer BCA 02/11 ke rek. a.n. Budi", required: true, type: "textarea" }] },
      mark_success: { title: "Tandai Berhasil Manual", message: "Gunakan hanya jika transaksi terbukti sukses di dashboard supplier.", confirm: "Tandai Berhasil",
        fields: [{ name: "sn", label: "SN / Token / Bukti", placeholder: "Salin dari dashboard Digiflazz", required: true, min: 3, error: "Minimal 3 karakter" },
                 { name: "note", label: "Catatan (opsional)", placeholder: "mis. Dicek di dashboard Digiflazz" }] },
      mark_failed: { title: "Tandai Gagal", message: "Pesanan akan masuk antrean refund. Pastikan transaksi benar-benar gagal di dashboard supplier, karena produk yang terlanjur terkirim tidak bisa ditarik kembali.", confirm: "Tandai Gagal", danger: true,
        fields: [{ name: "note", label: "Alasan", placeholder: "mis. Gagal di dashboard Digiflazz (rc 40)", required: true, type: "textarea" }] }
    };

    async function doAction(invoice, action, after, extra = {}, dialog = null) {
      let body = { invoice, action, ...extra };
      const dlg = dialog || ACTION_DIALOG[action];
      if (dlg) {
        const res = await ask(dlg);
        if (!res) return;
        body = { ...body, ...res };
      }
      try {
        const d = await api("admin/orders.php", { method: "POST", body });
        toast(d.message, "ok");
        closeModal();
        if (after) after(d.order);
        return d.order;
      } catch (err) { toast(err.message, "err"); }
    }

    async function orderDetail(invoice, reloadList) {
      openModal(`${modalHead(esc(invoice))}<div class="empty">Memuat detail…</div>`, true);
      let o;
      try { o = (await api("admin/orders.php?invoice=" + encodeURIComponent(invoice))).order; } catch (err) { toast(err.message, "err"); closeModal(); return; }
      renderDetail(o, reloadList);
    }

    function renderDetail(o, reloadList) {
      const a = o.admin;
      const kv = (rows) => `<div class="kv">${rows.filter(Boolean).map(([k, v]) => `<div><span>${k}</span><span>${v}</span></div>`).join("")}</div>`;
      const acts = [];
      const digital = a.kind === "digital";
      if (digital && o.status === "processing") {
        acts.push(`<button class="btn-primary btn-sm" data-act="deliver">${I.download} Kirim Produk</button>`);
        acts.push(`<button class="btn-outline btn-sm btn-danger" data-act="mark_failed">Batalkan &amp; Refund</button>`);
      }
      if (!digital && o.status === "processing") {
        acts.push(`<button class="btn-outline btn-sm" data-act="recheck">Cek Ulang ke Supplier</button>`);
        acts.push(`<button class="btn-outline btn-sm btn-success" data-act="mark_success">Tandai Berhasil</button>`);
        acts.push(`<button class="btn-outline btn-sm btn-danger" data-act="mark_failed">Tandai Gagal</button>`);
      }
      const manualRefunded = o.refund_status === "done" && a.refund_method !== "saldo";
      if (o.status === "failed") {
        if (!digital && !manualRefunded && a.retry_count < a.max_retry) acts.push(`<button class="btn-outline btn-sm" data-act="retry">↻ Kirim Ulang ke Supplier</button>`);
        if (o.refund_status === "pending") {
          acts.push(`<button class="btn-outline btn-sm btn-success" data-act="refund_balance">Refund ke Saldo</button>`);
          acts.push(`<button class="btn-outline btn-sm" data-act="refund_done">Refund Manual</button>`);
        }
        if (!manualRefunded && !digital) acts.push(`<button class="btn-outline btn-sm" data-act="mark_success">Ternyata Berhasil</button>`);
      }
      const card = openModal(`${modalHead(esc(o.invoice), statusBadge(o))}
        <div class="detail-grid">
          <div class="box"><h4>Pesanan</h4>${kv([
            ["Produk", esc(o.brand)], ["Item", esc(o.item)], ["SKU", `<code>${esc(a.sku)}</code>`], ["Tujuan", `<b>${esc(o.customer_no)}</b>`],
            o.customer_name && ["Nama", esc(o.customer_name)], ["Dibuat", fmtDate(o.created_at)], o.paid_at && ["Dibayar", fmtDate(o.paid_at)], o.completed_at && ["Selesai", fmtDate(o.completed_at)]])}</div>
          <div class="box"><h4>Pembayaran</h4>${kv([
            ["Harga jual", rupiah(o.price)], ["Biaya pembayaran", rupiah(o.fee)], ["Total dibayar", `<b>${rupiah(o.total)}</b>`],
            ["Metode", esc(o.payment.name || "-")], ["Gateway", esc(o.payment.provider || "-")], ["Referensi", `<code>${esc(a.pay_reference || "-")}</code>`]])}</div>
          <div class="box"><h4>Supplier</h4>${kv([
            ["Supplier", esc(a.supplier || "-")], ["Ref ID", `<code>${esc(a.supplier_ref || "-")}</code>`], a.retry_count && ["Kirim ulang", `${a.retry_count}× (maks ${a.max_retry})`],
            ["Status", esc(a.supplier_status || "-")], a.supplier_rc && ["RC", esc(a.supplier_rc)],
            ["Pesan", esc(a.supplier_message || "-")], ["SN", a.sn ? `<b>${esc(a.sn)}</b>` : "-"],
            ["Harga modal", a.supplier_price === null ? "-" : rupiah(a.supplier_price)],
            ["Laba kotor", a.margin === null ? "-" : `<span class="${a.margin < 0 ? "neg" : ""}">${rupiah(a.margin)}</span>`],
            a.last_checked_at && ["Dicek terakhir", fmtDate(a.last_checked_at)]])}</div>
          <div class="box"><h4>Pelanggan</h4>${kv([
            ["Nama", esc(o.user.name)], ["Email", `<a href="mailto:${esc(o.user.email)}" style="color:var(--primary)">${esc(o.user.email)}</a>`],
            ["No. HP", `<a href="https://wa.me/62${esc(String(o.user.phone).replace(/^0/, ""))}" target="_blank" rel="noopener" style="color:var(--primary)">${esc(o.user.phone)}</a>`],
            ["Saldo GDK", rupiah(a.user_balance)],
            o.refund_status !== "none" && ["Refund", o.refund_status === "done" ? `${a.refund_method === "saldo" ? "Ke Saldo GDK" : "Manual"} · ${fmtDate(a.refunded_at)}` : `<span class="neg">Menunggu</span>`]])}</div>
        </div>
        ${digital ? `<div class="actions-box"><h4>Produk Digital</h4>
          ${a.buyer_note ? `<p style="margin:0 0 10px;font-size:14px"><b>Catatan pembeli:</b> ${esc(a.buyer_note)}</p>` : `<p style="margin:0 0 10px;font-size:14px;color:var(--muted)">Pembeli tidak menulis catatan.</p>`}
          ${a.delivery ? `<div class="summary" style="margin:0">${(a.delivery.keys || []).map((k) => `<div><span>Kode lisensi</span><b style="word-break:break-all">${esc(k)}</b></div>`).join("")}
            ${a.delivery.download ? `<div><span>Unduhan</span><span>${esc(a.delivery.download.name || a.delivery.download.external || "-")} · dipakai ${a.download_count}×</span></div>` : ""}
            ${a.delivery.text ? `<div style="display:block"><span style="color:var(--muted)">Dikirim admin</span><div style="white-space:pre-wrap;margin-top:4px">${esc(a.delivery.text)}</div></div>` : ""}</div>`
            : o.status === "processing" ? `<div class="note" style="margin:0">${esc(a.supplier_message || "Menunggu dikirim admin")}</div>` : ""}
        </div>` : ""}
        ${acts.length ? `<div class="actions-box"><h4>Tindakan</h4><div class="actions-row">${acts.join("")}</div></div>` : ""}
        <div class="actions-box"><h4>Catatan admin</h4>
          <div class="field" style="margin-bottom:10px"><textarea id="adminNote" style="min-height:70px" placeholder="Catatan internal (tidak terlihat pelanggan)">${esc(a.admin_note || "")}</textarea></div>
          <button class="btn-outline btn-sm" data-act="note">Simpan Catatan</button></div>
        <details class="logs"><summary>Log API (${o.logs.length})</summary>
          ${o.logs.map((l) => `<div class="log-item"><header><span><b>${esc(l.channel)}</b> · ${esc(l.action)}${l.http ? ` · HTTP ${l.http}` : ""}</span><span>${fmtDate(l.at)}</span></header>
            ${l.request ? `<pre>→ ${esc(l.request)}</pre>` : ""}${l.response ? `<pre>← ${esc(l.response)}</pre>` : ""}</div>`).join("") || `<p style="color:var(--muted);font-size:13px">Belum ada log.</p>`}
        </details>`, true);

      $$("[data-act]", card).forEach((b) => b.addEventListener("click", async () => {
        const act = b.dataset.act;
        const extra = act === "note" ? { note: $("#adminNote", card).value.trim() } : {};
        b.disabled = true;
        let dialog = null;
        const refundedToSaldo = o.refund_status === "done" && a.refund_method === "saldo";
        if (act === "deliver") {
          dialog = { title: "Kirim Produk Digital", confirm: "Kirim ke Pembeli",
            message: `Isi akan langsung tampil di halaman pesanan pembeli <b>${esc(o.user.email)}</b>${a.buyer_note ? ` (catatan: ${esc(a.buyer_note)})` : ""}. Link yang diawali https:// otomatis bisa diklik.`,
            fields: [{ name: "content", label: "Isi pengiriman", type: "textarea", required: true, min: 3, placeholder: "Link unduhan, akun & password, kode lisensi, atau instruksi…", error: "Minimal 3 karakter" }] };
        }
        if (act === "mark_failed" && digital) {
          dialog = { ...ACTION_DIALOG.mark_failed, title: "Batalkan & Refund", message: "Pesanan dibatalkan dan dana otomatis dikembalikan ke Saldo GDK pembeli.", confirm: "Batalkan Pesanan" };
        }
        if (act === "retry") {
          dialog = { title: "Kirim Ulang ke Supplier", confirm: "Kirim Ulang",
            message: `Pesanan dikirim ulang dengan nomor transaksi baru <code>${esc(o.invoice)}R${a.retry_count + 1}</code> (percobaan ${a.retry_count + 1} dari ${a.max_retry}).` +
              (refundedToSaldo ? `<br><br><b>Refund ${rupiah(o.total)}</b> yang sudah masuk ke Saldo GDK pelanggan akan <b>ditarik kembali</b> (saldo pelanggan saat ini ${rupiah(a.user_balance)}). Bila gagal lagi, dana otomatis dikembalikan ke saldo.` : "") };
        }
        if (act === "mark_success" && refundedToSaldo) {
          dialog = { ...ACTION_DIALOG.mark_success, message: ACTION_DIALOG.mark_success.message + `<br><br>Refund ${rupiah(o.total)} di Saldo GDK pelanggan akan ditarik kembali.` };
        }
        const updated = await doAction(o.invoice, act, null, extra, dialog);
        if (updated) { renderDetail(updated, reloadList); if (reloadList) reloadList(); }
        else if (document.body.contains(b)) b.disabled = false;
        // Dialog ask() mengganti isi modal; bila dibatalkan, tampilkan detail lagi
        if (!updated && (dialog || ACTION_DIALOG[act])) renderDetail(o, reloadList);
      }));
    }

    /* =========================================================
       TAB: PRODUK & HARGA
       ========================================================= */
    const productsState = { brand: "", state: "all", q: "", page: 1 };
    let brandsCache = [];

    async function tabProduk() {
      const st = productsState;
      view.innerHTML = `
        <div class="filters">
          <select id="pBrand" aria-label="Brand"><option value="">Semua brand</option></select>
          <select id="pState" aria-label="Status">
            ${[["all", "Semua status"], ["active", "Aktif & tersedia"], ["inactive", "Dinonaktifkan admin"], ["unavailable", "Gangguan di supplier"]]
              .map(([v, l]) => `<option value="${v}" ${st.state === v ? "selected" : ""}>${l}</option>`).join("")}
          </select>
          <input class="grow" id="pQ" type="search" placeholder="Cari nama produk, SKU, brand…" value="${esc(st.q)}">
          <button class="btn-outline btn-sm" id="btnMarkup">Markup Massal</button>
          <button class="btn-outline btn-sm" id="btnBrands">Pengaturan Brand</button>
        </div>
        <section class="panel" id="pPanel"><div class="empty">Memuat produk…</div></section>`;
      const panel = $("#pPanel");

      const load = async () => {
        loading(panel);
        let d;
        try { d = await api("admin/products.php?" + new URLSearchParams({ brand: st.brand, state: st.state, q: st.q, page: st.page, per: 50 })); }
        catch (err) { panel.innerHTML = `<div class="empty">${esc(err.message)}</div>`; loaded(panel); return; }
        loaded(panel);
        brandsCache = d.brands;
        const sel = $("#pBrand");
        if (sel.options.length <= 1) sel.insertAdjacentHTML("beforeend", d.brands.map((b) => `<option value="${esc(b.slug)}">${esc(b.name)} (${b.live}/${b.total})</option>`).join(""));
        sel.value = st.brand;

        panel.innerHTML = `<div class="card-head"><span style="color:var(--ink-2);font-size:14px"><b>${num(d.total)}</b> produk · markup otomatis saat sinkron: Rp ${num(d.markup.flat)} + ${d.markup.percent}%</span>
            <small style="color:var(--muted)">Kunci harga 🔒 agar tidak ditimpa sinkron otomatis</small></div>
          ${d.products.length ? `<div class="table-wrap"><table class="tx admin"><thead><tr>
            <th>Produk</th><th class="num">Modal</th><th class="num">Harga jual</th><th class="num">Laba</th><th>Kunci</th><th>Aktif</th><th>Supplier</th><th></th></tr></thead>
            <tbody>${d.products.map((p) => `<tr data-sku="${esc(p.sku)}" data-modal="${p.price_modal}" data-type="${p.type}">
              <td>${esc(p.name)}<br><small>${esc(p.brand_name)} · <code>${esc(p.sku)}</code></small></td>
              <td class="num">${p.type === "postpaid" ? "<small>tagihan</small>" : rupiah(p.price_modal)}</td>
              <td class="num">${p.type === "postpaid" ? "<small>dari inquiry</small>" : `<input class="price-input" inputmode="numeric" value="${p.price_sell}" data-orig="${p.price_sell}" aria-label="Harga jual ${esc(p.name)}">`}</td>
              <td class="num margin">${p.type === "postpaid" ? "-" : marginHtml(p.price_sell, p.price_modal)}</td>
              <td><label class="switch" title="Kunci harga"><input type="checkbox" data-f="price_locked" ${p.price_locked ? "checked" : ""} data-orig="${p.price_locked ? 1 : 0}" aria-label="Kunci harga"><span></span></label></td>
              <td><label class="switch" title="Tampilkan di website"><input type="checkbox" data-f="is_active" ${p.is_active ? "checked" : ""} data-orig="${p.is_active ? 1 : 0}" aria-label="Aktif"><span></span></label></td>
              <td>${p.supplier_ok ? `<span class="status ok">Tersedia</span>` : `<span class="status bad">Gangguan</span>`}</td>
              <td><button class="btn-primary btn-sm save" hidden>Simpan</button></td>
            </tr>`).join("")}</tbody></table></div>`
          : `<div class="empty" style="padding:30px">Tidak ada produk yang cocok.</div>`}`;
        if (d.products.length) panel.appendChild(pager(d, (pg) => { st.page = pg; load(); }));

        $$("tr[data-sku]", panel).forEach((tr) => {
          const save = $(".save", tr);
          const inp = $(".price-input", tr);
          const check = () => {
            let dirty = false;
            if (inp) {
              const v = parseInt(inp.value.replace(/\D/g, ""), 10) || 0;
              dirty = dirty || v !== +inp.dataset.orig;
              $(".margin", tr).innerHTML = marginHtml(v, +tr.dataset.modal);
              inp.classList.toggle("err", tr.dataset.type === "prepaid" && +tr.dataset.modal > 0 && v < +tr.dataset.modal);
            }
            $$("input[type=checkbox]", tr).forEach((c) => { if ((c.checked ? 1 : 0) !== +c.dataset.orig) dirty = true; });
            tr.classList.toggle("dirty", dirty); save.hidden = !dirty;
          };
          tr.addEventListener("input", check); tr.addEventListener("change", check);
          if (inp) inp.addEventListener("keydown", (ev) => { if (ev.key === "Enter" && !save.hidden) save.click(); });
          save.addEventListener("click", async () => {
            const body = { action: "update_product", sku: tr.dataset.sku };
            if (inp) body.price_sell = parseInt(inp.value.replace(/\D/g, ""), 10) || 0;
            $$("input[type=checkbox]", tr).forEach((c) => { body[c.dataset.f] = c.checked; });
            save.disabled = true;
            try {
              const r = await api("admin/products.php", { method: "POST", body });
              toast(r.message, "ok");
              if (inp) { inp.dataset.orig = body.price_sell; inp.value = body.price_sell; }
              $$("input[type=checkbox]", tr).forEach((c) => { c.dataset.orig = c.checked ? 1 : 0; });
              check();
            } catch (err) { toast(err.message, "err"); }
            save.disabled = false;
          });
        });
      };

      $("#pBrand").addEventListener("change", (ev) => { st.brand = ev.target.value; st.page = 1; load(); });
      $("#pState").addEventListener("change", (ev) => { st.state = ev.target.value; st.page = 1; load(); });
      $("#pQ").addEventListener("input", debounce((ev) => { st.q = ev.target.value.trim(); st.page = 1; load(); }));
      $("#btnMarkup").addEventListener("click", () => markupDialog(load));
      $("#btnBrands").addEventListener("click", () => brandsDialog(load));
      load();
    }

    function marginHtml(sell, modal) {
      if (!modal) return "-";
      const m = sell - modal; const pct = modal ? (m / modal * 100) : 0;
      return `<span class="${m < 0 ? "neg" : ""}">${rupiah(m)}</span><br><small>${pct.toLocaleString("id-ID", { maximumFractionDigits: 1 })}%</small>`;
    }

    function markupDialog(reload) {
      const card = openModal(`${modalHead("Markup Massal", "Hitung ulang harga jual dari harga modal")}
        <div class="note info">Harga jual = modal + markup tetap + persen, dibulatkan ke atas. Produk dengan harga <b>terkunci</b> dan produk pascabayar dilewati.</div>
        <form id="mkForm" novalidate>
          <div class="field"><label for="mkBrand">Berlaku untuk</label><select id="mkBrand"><option value="all">Semua brand prabayar</option>
            ${brandsCache.filter((b) => b.type === "prepaid").map((b) => `<option value="${esc(b.slug)}" ${productsState.brand === b.slug ? "selected" : ""}>${esc(b.name)}</option>`).join("")}</select></div>
          <div class="row-2">
            <div class="field"><label for="mkFlat">Markup tetap (Rp)</label><input id="mkFlat" inputmode="numeric" value="1000"></div>
            <div class="field"><label for="mkPct">Markup persen (%)</label><input id="mkPct" inputmode="decimal" value="0"></div>
          </div>
          <div class="field"><label for="mkRound">Bulatkan ke atas kelipatan (Rp)</label><input id="mkRound" inputmode="numeric" value="100"></div>
          <div class="summary" id="mkPreview"></div>
          <div class="actions-row" style="justify-content:flex-end"><button type="button" class="btn-outline btn-sm" data-close>Batal</button><button type="submit" class="btn-primary btn-sm">Terapkan</button></div>
        </form>`);
      const vals = () => ({ flat: parseInt($("#mkFlat", card).value, 10) || 0, percent: parseFloat(String($("#mkPct", card).value).replace(",", ".")) || 0, round: parseInt($("#mkRound", card).value, 10) || 1 });
      const calc = (modal, m) => Math.ceil((modal + m.flat + modal * m.percent / 100) / m.round) * m.round;
      const preview = () => {
        const m = vals();
        $("#mkPreview", card).innerHTML = [5000, 20000, 100000].map((v) => `<div><span>Modal ${rupiah(v)}</span><span>→ <b>${rupiah(calc(v, m))}</b> <small style="color:var(--muted)">(+${rupiah(calc(v, m) - v)})</small></span></div>`).join("");
      };
      card.addEventListener("input", preview); preview();
      $("#mkForm", card).addEventListener("submit", async (ev) => {
        ev.preventDefault();
        const sel = $("#mkBrand", card);
        const target = sel.options[sel.selectedIndex].text;
        if (!confirm(`Terapkan markup ke ${target}? Harga jual produk yang tidak dikunci akan diganti.`)) return;
        try {
          const r = await api("admin/products.php", { method: "POST", body: { action: "bulk_markup", brand: sel.value, ...vals() } });
          toast(r.message, "ok"); closeModal(); reload();
        } catch (err) { toast(err.message, "err"); }
      });
    }

    function brandsDialog(reload) {
      const card = openModal(`${modalHead("Pengaturan Brand", "Tampilkan/sembunyikan brand, label terlaris, biaya admin & pemetaan Digiflazz")}
        <div class="note info">Kolom <b>Kategori DF</b> &amp; <b>Brand DF</b> harus sama dengan nama di price list Digiflazz (lihat laporan sinkron di tab Supplier). Kosongkan Kategori DF untuk mencocokkan berdasarkan brand saja.</div>
        <div class="table-wrap"><table class="tx admin" style="min-width:900px"><thead><tr><th>Brand</th><th>Aktif</th><th>Terlaris</th><th class="num">Admin (pasca)</th><th>Kategori DF</th><th>Brand DF</th><th></th></tr></thead>
        <tbody>${brandsCache.map((b) => `<tr data-slug="${esc(b.slug)}">
          <td><b>${esc(b.name)}</b><br><small>${b.type === "postpaid" ? "Pascabayar" : "Prabayar"} · ${b.live}/${b.total} produk tampil</small></td>
          <td><label class="switch"><input type="checkbox" data-f="is_active" ${b.is_active ? "checked" : ""} aria-label="Aktif"><span></span></label></td>
          <td><label class="switch"><input type="checkbox" data-f="is_hot" ${b.is_hot ? "checked" : ""} aria-label="Terlaris"><span></span></label></td>
          <td class="num">${b.type === "postpaid" ? `<input class="price-input" style="width:90px" data-f="admin_fee" inputmode="numeric" value="${b.admin_fee}">` : "-"}</td>
          <td><input class="text-input" data-f="df_category" value="${esc(b.df_category || "")}" placeholder="(semua)"></td>
          <td><input class="text-input" data-f="df_brand" value="${esc(b.df_brand || "")}" placeholder="mis. TELKOMSEL"></td>
          <td><button class="btn-primary btn-sm save" hidden>Simpan</button></td></tr>`).join("")}</tbody></table></div>`, true);
      $$("tr[data-slug]", card).forEach((tr) => {
        const save = $(".save", tr);
        const dirty = () => { tr.classList.add("dirty"); save.hidden = false; };
        tr.addEventListener("input", dirty); tr.addEventListener("change", dirty);
        save.addEventListener("click", async () => {
          const body = { action: "update_brand", slug: tr.dataset.slug };
          $$("[data-f]", tr).forEach((el) => { body[el.dataset.f] = el.type === "checkbox" ? el.checked : el.value.trim(); });
          if ("admin_fee" in body) body.admin_fee = parseInt(body.admin_fee, 10) || 0;
          save.disabled = true;
          try { const r = await api("admin/products.php", { method: "POST", body }); toast(r.message, "ok"); tr.classList.remove("dirty"); save.hidden = true; reload(); }
          catch (err) { toast(err.message, "err"); }
          save.disabled = false;
        });
      });
    }

    /* =========================================================
       TAB: PRODUK DIGITAL
       ========================================================= */
    const digitalState = { category: "", q: "" };
    const DELIV_LABEL = { download: "Unduh file", license: "Kode lisensi", manual: "Manual" };
    async function tabDigital() {
      const st = digitalState;
      view.innerHTML = `
        <div class="filters">
          <select id="dCat" aria-label="Kategori"><option value="">Semua kategori</option></select>
          <input class="grow" id="dQ" type="search" placeholder="Cari nama produk atau toko…" value="${esc(st.q)}">
          <a class="btn-outline btn-sm" href="toko.html" target="_blank" rel="noopener">Lihat Toko ↗</a>
          <button class="btn-primary btn-sm" id="dNew">+ Tambah Produk</button>
        </div>
        <section class="panel" id="dPanel"><div class="empty">Memuat produk digital…</div></section>`;
      const panel = $("#dPanel");
      let cats = [];
      const load = async () => {
        loading(panel);
        let d;
        try { d = await api("admin/digital.php?" + new URLSearchParams(st)); } catch (err) { panel.innerHTML = `<div class="empty">${esc(err.message)}</div>`; loaded(panel); return; }
        loaded(panel); cats = d.categories;
        const sel = $("#dCat");
        if (sel.options.length <= 1) sel.insertAdjacentHTML("beforeend", d.categories.map((c) => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join(""));
        sel.value = st.category;
        const waiting = d.products.reduce((a, p) => a + p.waiting, 0);
        const delivCell = (p) => {
          if (p.delivery === "license") return `<span class="status ${p.stock ? "ok" : "bad"}">${DELIV_LABEL.license}</span><br><small>stok ${num(p.stock)}</small>`;
          if (p.delivery === "download") return `<span class="status ${p.has_file || p.has_url ? "ok" : "bad"}">${DELIV_LABEL.download}</span><br><small>${p.has_file ? "file terunggah" : p.has_url ? "link eksternal" : "belum ada file"}</small>`;
          return `<span class="status muted">Manual</span>`;
        };
        panel.innerHTML = `<div class="card-head"><span style="color:var(--ink-2);font-size:14px"><b>${num(d.products.length)}</b> produk${waiting ? ` · <a href="#" id="dWaiting" style="color:#b54708;font-weight:600">${waiting} pesanan menunggu dikirim</a>` : ""}</span>
            <small style="color:var(--muted)">Batas upload server: ${esc(d.upload_limit || "-")}</small></div>
          ${d.products.length ? `<div class="table-wrap"><table class="tx admin"><thead><tr>
            <th>Produk</th><th class="num">Harga</th><th>Cara kirim</th><th class="num">Terjual</th><th>Aktif</th><th>Pilihan</th><th></th></tr></thead>
            <tbody>${d.products.map((p) => `<tr data-id="${p.id}">
              <td><div class="dg-row"><span class="dg-thumb">${p.image ? `<img src="${esc(p.image)}" alt="">` : I.spark}</span>
                <div><b>${esc(p.name)}</b><br><small>${esc(p.category)}${p.store ? " · " + esc(p.store) : ""}</small></div></div></td>
              <td class="num">${p.price_strike ? `<small><s>${rupiah(p.price_strike)}</s></small><br>` : ""}<b>${rupiah(p.price)}</b></td>
              <td>${delivCell(p)}</td>
              <td class="num">${num(p.sold)}${p.waiting ? `<br><small class="neg">${p.waiting} menunggu</small>` : ""}</td>
              <td><label class="switch"><input type="checkbox" data-toggle="is_active" ${p.is_active ? "checked" : ""} aria-label="Aktif"><span></span></label></td>
              <td><label class="switch"><input type="checkbox" data-toggle="is_featured" ${p.is_featured ? "checked" : ""} aria-label="Pilihan"><span></span></label></td>
              <td><div class="actions-row" style="flex-wrap:nowrap">
                <button class="btn-outline btn-sm" data-edit>Ubah</button>
                ${p.delivery === "license" ? `<button class="btn-outline btn-sm" data-lic>Lisensi</button>` : ""}
                <a class="btn-outline btn-sm" href="produk-digital.html?p=${encodeURIComponent(p.slug)}" target="_blank" rel="noopener" title="Lihat di toko">↗</a>
              </div></td></tr>`).join("")}</tbody></table></div>`
          : `<div class="empty" style="padding:30px">Belum ada produk digital.</div>`}`;
        const w = $("#dWaiting", panel); if (w) w.addEventListener("click", (e) => { e.preventDefault(); ordersState.status = "digital_waiting"; go("pesanan"); });
        $$("tr[data-id]", panel).forEach((tr) => {
          const id = +tr.dataset.id;
          $$("[data-toggle]", tr).forEach((c) => c.addEventListener("change", async () => {
            try { await api("admin/digital.php", { method: "POST", body: { action: "toggle", id, field: c.dataset.toggle } }); toast("Disimpan.", "ok"); }
            catch (err) { toast(err.message, "err"); c.checked = !c.checked; }
          }));
          $("[data-edit]", tr).addEventListener("click", () => digitalForm(id, cats, load));
          const lic = $("[data-lic]", tr); if (lic) lic.addEventListener("click", () => licenseDialog(id, load));
        });
      };
      $("#dCat").addEventListener("change", (e) => { st.category = e.target.value; load(); });
      $("#dQ").addEventListener("input", debounce((e) => { st.q = e.target.value.trim(); load(); }));
      $("#dNew").addEventListener("click", () => digitalForm(0, cats, load));
      load();
    }

    async function digitalForm(id, cats, reload) {
      let p = { name: "", category_id: cats[0] ? cats[0].id : "", price: "", delivery_type: "manual", is_active: 1, is_featured: 0, sort_order: 0, buyer_note_required: 0 };
      if (id) {
        openModal(`${modalHead("Ubah Produk")}<div class="empty">Memuat…</div>`, true);
        try { p = (await api("admin/digital.php?id=" + id)).product; } catch (err) { toast(err.message, "err"); closeModal(); return; }
      }
      const v = (k) => esc(p[k] ?? "");
      const card = openModal(`${modalHead(id ? "Ubah Produk Digital" : "Tambah Produk Digital", id ? `<a href="produk-digital.html?p=${encodeURIComponent(p.slug)}" target="_blank" rel="noopener" style="color:var(--primary)">${esc(p.slug)} ↗</a>` : "")}
        <form id="dForm" novalidate>
          <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="${id || ""}">
          <div class="row-2">
            <div class="field"><label>Nama produk</label><input name="name" value="${v("name")}" required><span class="err"></span></div>
            <div class="field"><label>Kategori</label><select name="category_id">${cats.map((c) => `<option value="${esc(c.id)}" ${c.id === p.category_id ? "selected" : ""}>${esc(c.name)}</option>`).join("")}</select><span class="err"></span></div>
          </div>
          <div class="row-2">
            <div class="field"><label>Harga jual (Rp)</label><input name="price" inputmode="numeric" value="${v("price")}"><span class="err"></span></div>
            <div class="field"><label>Harga coret (Rp, opsional)</label><input name="price_strike" inputmode="numeric" value="${v("price_strike")}" placeholder="kosongkan bila tidak diskon"><span class="err"></span></div>
          </div>
          <div class="row-2">
            <div class="field"><label>Nama toko / penjual</label><input name="store_name" value="${v("store_name")}" placeholder="mis. Generasi Berkarya Store"></div>
            <div class="field"><label>Slug URL</label><input name="slug" value="${v("slug")}" placeholder="otomatis dari nama"><span class="err"></span></div>
          </div>
          <div class="field"><label>Deskripsi singkat</label><input name="short_desc" maxlength="300" value="${v("short_desc")}" placeholder="1–2 kalimat yang tampil di bawah judul"></div>
          <div class="field"><label>Deskripsi lengkap</label><textarea name="description" style="min-height:140px" placeholder="## Fitur utama&#10;- Poin pertama&#10;- Poin kedua">${v("description")}</textarea>
            <span class="hint">Baris diawali <code>## </code> menjadi subjudul, <code>- </code> menjadi poin.</span></div>
          <div class="row-2">
            <div class="field"><label>Platform</label><input name="platform" value="${v("platform")}" placeholder="mis. Windows / macOS"></div>
            <div class="field"><label>Jenis lisensi</label><input name="license_type" value="${v("license_type")}" placeholder="mis. Lisensi 1 website"></div>
          </div>
          <div class="row-2">
            <div class="field"><label>Versi</label><input name="version" value="${v("version")}" placeholder="mis. 3.21"></div>
            <div class="field"><label>Urutan tampil</label><input name="sort_order" inputmode="numeric" value="${v("sort_order")}"></div>
          </div>
          <div class="field"><label>Gambar produk (JPG/PNG/WEBP, maks. 3 MB, rasio 4:3)</label>
            <div class="dg-img-row">${p.image ? `<img src="${esc(p.image)}" alt="">` : `<span class="dg-thumb lg">${I.spark}</span>`}
              <div><input type="file" name="image" accept="image/jpeg,image/png,image/webp">${p.image ? `<label class="check-row" style="margin:6px 0 0"><input type="checkbox" name="remove_image" value="1"> <span>Hapus gambar (pakai sampul otomatis)</span></label>` : `<span class="hint">Kosongkan untuk sampul otomatis sesuai kategori.</span>`}</div></div>
            <span class="err"></span></div>

          <div class="actions-box" style="margin:6px 0 16px"><h4>Pengiriman ke pembeli</h4>
            <div class="field"><label>Cara kirim</label><select name="delivery_type" id="dDeliv">
              <option value="download" ${p.delivery_type === "download" ? "selected" : ""}>Unduh file — instan setelah bayar</option>
              <option value="license" ${p.delivery_type === "license" ? "selected" : ""}>Kode lisensi — instan, diambil dari stok</option>
              <option value="manual" ${p.delivery_type === "manual" ? "selected" : ""}>Manual — admin mengirim setelah bayar</option></select></div>
            <div data-for="download">
              <div class="field"><label>File produk (${["zip", "rar", "7z", "pdf", "epub", "apk", "exe", "msi", "dmg", "iso"].join(", ")})</label>
                ${p.has_file ? `<p style="margin:0 0 6px;font-size:13.5px">File saat ini: <b>${esc(p.file_name || "-")}</b></p>` : ""}
                <input type="file" name="file">${p.has_file ? `<label class="check-row" style="margin:6px 0 0"><input type="checkbox" name="remove_file" value="1"> <span>Hapus file</span></label>` : ""}<span class="err"></span></div>
              <div class="field"><label>…atau link unduhan eksternal (https://)</label><input name="file_url" value="${v("file_url")}" placeholder="https://drive.google.com/…"><span class="hint">Untuk file besar. Link hanya tampil ke pembeli yang sudah bayar.</span><span class="err"></span></div>
            </div>
            <div data-for="license"><div class="note info" style="margin:0 0 12px">Simpan dulu, lalu isi stok kode lewat tombol <b>Lisensi</b> di daftar produk. Saat stok habis, produk otomatis tidak bisa dibeli.</div></div>
            <div data-for="manual"><div class="note info" style="margin:0 0 12px">Pesanan masuk antrean <b>Digital: menunggu dikirim</b>. Buka pesanan lalu klik <b>Kirim Produk</b>.</div></div>
            <div class="field"><label>Pesan/instruksi untuk pembeli setelah bayar (opsional)</label><textarea name="delivery_note" style="min-height:70px" placeholder="mis. Cara instal / aktivasi">${v("delivery_note")}</textarea></div>
            <div class="row-2">
              <div class="field"><label>Pertanyaan ke pembeli saat checkout</label><input name="buyer_note_label" value="${v("buyer_note_label")}" placeholder="mis. Email akun / domain website (kosongkan bila tidak perlu)"></div>
              <div class="field"><label>&nbsp;</label><label class="check-row" style="margin:10px 0 0"><input type="checkbox" name="buyer_note_required" value="1" ${+p.buyer_note_required ? "checked" : ""}> <span>Wajib diisi pembeli</span></label></div>
            </div>
          </div>
          <div class="actions-row" style="justify-content:space-between;align-items:center">
            <div class="actions-row">
              <label class="check-row" style="margin:0"><input type="checkbox" name="is_active" value="1" ${+p.is_active ? "checked" : ""}> <span>Aktif (tampil di toko)</span></label>
              <label class="check-row" style="margin:0"><input type="checkbox" name="is_featured" value="1" ${+p.is_featured ? "checked" : ""}> <span>Produk pilihan (beranda)</span></label>
            </div>
            <div class="actions-row">
              ${id ? `<button type="button" class="btn-outline btn-sm btn-danger" id="dDel">Hapus</button>` : ""}
              <button type="button" class="btn-outline btn-sm" data-close>Batal</button>
              <button type="submit" class="btn-primary btn-sm">Simpan</button>
            </div>
          </div>
        </form>`, true);
      const form = $("#dForm", card);
      const syncDeliv = () => $$("[data-for]", form).forEach((el) => { el.hidden = el.dataset.for !== form.delivery_type.value; });
      form.delivery_type.addEventListener("change", syncDeliv); syncDeliv();
      form.addEventListener("input", (e) => { const f = e.target.closest(".field"); if (f) f.classList.remove("invalid"); });
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const btn = $("button[type=submit]", form); btn.disabled = true; btn.textContent = "Menyimpan…";
        try {
          const r = await api("admin/digital.php", { method: "POST", body: new FormData(form) });
          toast(r.message, "ok"); closeModal(); reload();
        } catch (err) {
          const f = err.field && form.elements[err.field];
          const fld = f && (f.closest ? f.closest(".field") : null);
          if (fld) { $(".err", fld).textContent = err.message; fld.classList.add("invalid"); fld.scrollIntoView({ block: "center", behavior: "smooth" }); }
          else toast(err.message, "err");
          btn.disabled = false; btn.textContent = "Simpan";
        }
      });
      const del = $("#dDel", form);
      if (del) del.addEventListener("click", async () => {
        if (!confirm(`Hapus "${p.name}"? Produk yang pernah dipesan hanya akan dinonaktifkan.`)) return;
        try { const r = await api("admin/digital.php", { method: "POST", body: { action: "delete", id } }); toast(r.message, "ok"); closeModal(); reload(); }
        catch (err) { toast(err.message, "err"); }
      });
    }

    async function licenseDialog(id, reload) {
      openModal(`${modalHead("Stok Kode Lisensi")}<div class="empty">Memuat…</div>`, true);
      let d;
      try { d = await api("admin/digital.php?licenses=" + id); } catch (err) { toast(err.message, "err"); closeModal(); return; }
      const avail = d.licenses.filter((l) => l.status === "available").length;
      const card = openModal(`${modalHead("Stok Kode Lisensi", esc(d.product.name))}
        <div class="ledger-head"><span>Stok tersedia</span><b>${num(avail)}</b></div>
        <form id="licForm" class="actions-box" style="margin:0 0 14px" novalidate>
          <h4>Tambah kode</h4>
          <div class="field"><textarea name="keys" style="min-height:110px;font-family:ui-monospace,monospace" placeholder="Satu kode per baris&#10;XXXX-XXXX-XXXX-0001&#10;XXXX-XXXX-XXXX-0002"></textarea><span class="err"></span>
            <span class="hint">Kode disimpan terenkripsi. Kode ganda otomatis dilewati.</span></div>
          <button type="submit" class="btn-primary btn-sm">Tambah ke Stok</button>
        </form>
        ${d.licenses.length ? `<div class="table-wrap"><table class="tx"><thead><tr><th>Kode</th><th>Status</th><th>Pesanan</th><th></th></tr></thead>
          <tbody>${d.licenses.map((l) => `<tr><td><code>${esc(l.key)}</code></td>
            <td>${l.status === "sold" ? `<span class="status muted">Terjual</span>` : `<span class="status ok">Tersedia</span>`}</td>
            <td>${l.invoice ? `${esc(l.invoice)}<br><small>${fmtDate(l.sold_at)}</small>` : `<small>ditambah ${fmtDate(l.at)}</small>`}</td>
            <td>${l.status === "available" ? `<button class="btn-outline btn-sm btn-danger" data-del="${l.id}">Hapus</button>` : ""}</td></tr>`).join("")}</tbody></table></div>`
          : `<div class="empty" style="padding:20px">Belum ada kode.</div>`}`, true);
      $("#licForm", card).addEventListener("submit", async (e) => {
        e.preventDefault();
        try { const r = await api("admin/digital.php", { method: "POST", body: { action: "add_licenses", id, keys: e.target.keys.value } }); toast(r.message, "ok"); licenseDialog(id, reload); reload(); }
        catch (err) { const f = $(".field", e.target); $(".err", f).textContent = err.message; f.classList.add("invalid"); }
      });
      $$("[data-del]", card).forEach((b) => b.addEventListener("click", async () => {
        if (!confirm("Hapus kode ini dari stok?")) return;
        try { const r = await api("admin/digital.php", { method: "POST", body: { action: "delete_license", id, license_id: +b.dataset.del } }); toast(r.message, "ok"); licenseDialog(id, reload); reload(); }
        catch (err) { toast(err.message, "err"); }
      }));
    }

    /* =========================================================
       TAB: PENGGUNA
       ========================================================= */
    const usersState = { q: "", page: 1 };
    async function tabPengguna() {
      const st = usersState;
      view.innerHTML = `<div class="filters"><input class="grow" id="uQ" type="search" placeholder="Cari nama, email, nomor HP…" value="${esc(st.q)}"></div>
        <section class="panel" id="uPanel"><div class="empty">Memuat pengguna…</div></section>`;
      const panel = $("#uPanel");
      const load = async () => {
        loading(panel);
        let d;
        try { d = await api("admin/users.php?" + new URLSearchParams({ q: st.q, page: st.page, per: 25 })); }
        catch (err) { panel.innerHTML = `<div class="empty">${esc(err.message)}</div>`; loaded(panel); return; }
        loaded(panel);
        panel.innerHTML = d.users.length ? `<div class="table-wrap"><table class="tx admin"><thead><tr>
            <th>Pengguna</th><th>No. HP</th><th>Peran</th><th>Status</th><th class="num">Saldo GDK</th><th class="num">Transaksi</th><th class="num">Total belanja</th><th>Login terakhir</th><th></th></tr></thead>
          <tbody>${d.users.map((u) => `<tr data-email="${esc(u.email)}">
            <td><b>${esc(u.name)}</b>${u.is_self ? ` <small>(Anda)</small>` : ""}<br><small>${esc(u.email)}</small></td>
            <td>${esc(u.phone)}</td>
            <td>${u.role === "admin" ? `<span class="status ok">Admin</span>` : `<span class="status muted">Pelanggan</span>`}${u.twofa ? ` <span class="status ok" title="Verifikasi dua langkah aktif">2FA</span>` : ""}</td>
            <td>${u.is_active ? `<span class="status ok">Aktif</span>` : `<span class="status bad">Nonaktif</span>`}</td>
            <td class="num"><a href="#" data-ledger="${esc(u.email)}" style="color:var(--primary)">${rupiah(u.balance)}</a></td>
            <td class="num">${num(u.orders)}</td><td class="num">${rupiah(u.spent)}</td>
            <td>${u.last_login_at ? fmtDate(u.last_login_at) : "-"}<br><small>daftar ${fmtDate(u.created_at)}</small></td>
            <td>${u.is_self ? "" : `<div class="actions-row" style="flex-wrap:nowrap">
              <button class="btn-outline btn-sm ${u.is_active ? "btn-danger" : "btn-success"}" data-a="toggle_active">${u.is_active ? "Nonaktifkan" : "Aktifkan"}</button>
              <button class="btn-outline btn-sm" data-a="${u.role === "admin" ? "make_customer" : "make_admin"}">${u.role === "admin" ? "Cabut admin" : "Jadikan admin"}</button>
              ${u.twofa ? `<button class="btn-outline btn-sm" data-a="reset_2fa">Reset 2FA</button>` : ""}</div>`}</td>
          </tr>`).join("")}</tbody></table></div>` : `<div class="empty" style="padding:30px">Tidak ada pengguna yang cocok.</div>`;
        if (d.users.length) panel.appendChild(pager(d, (p) => { st.page = p; load(); }));
        $$("[data-a]", panel).forEach((b) => b.addEventListener("click", async () => {
          const email = b.closest("tr").dataset.email;
          const label = { toggle_active: b.textContent.trim().toLowerCase(), make_admin: "jadikan admin", make_customer: "cabut akses admin", reset_2fa: "reset verifikasi dua langkah (lakukan hanya setelah memastikan identitas pemilik akun)" }[b.dataset.a];
          if (!confirm(`Yakin ingin ${label} untuk ${email}?`)) return;
          try { const r = await api("admin/users.php", { method: "POST", body: { email, action: b.dataset.a } }); toast(r.message, "ok"); load(); }
          catch (err) { toast(err.message, "err"); }
        }));
      };
      panel.addEventListener("click", (ev) => { const a = ev.target.closest("[data-ledger]"); if (a) { ev.preventDefault(); ledgerDialog(a.dataset.ledger, load); } });
      $("#uQ").addEventListener("input", debounce((ev) => { st.q = ev.target.value.trim(); st.page = 1; load(); }));
      load();
    }

    async function ledgerDialog(email, reload) {
      openModal(`${modalHead("Saldo GDK")}<div class="empty">Memuat…</div>`, true);
      let d;
      try { d = await api("admin/users.php?ledger=" + encodeURIComponent(email)); } catch (err) { toast(err.message, "err"); closeModal(); return; }
      const self = email === user.email;
      const card = openModal(`${modalHead("Saldo GDK", esc(d.user.name) + " · " + esc(d.user.email))}
        <div class="ledger-head"><span>Saldo saat ini</span><b>${rupiah(d.user.balance)}</b></div>
        ${self ? `<div class="note info">Anda tidak dapat menyesuaikan saldo akun Anda sendiri.</div>` : `
        <form id="adjForm" class="actions-box" style="margin:0 0 14px" novalidate>
          <h4>Penyesuaian saldo</h4>
          <div class="row-2">
            <div class="field"><label for="adjAmt">Nominal (Rp)</label><input id="adjAmt" name="amount" inputmode="numeric" placeholder="50000 atau -50000"><span class="err"></span></div>
            <div class="field"><label for="adjNote">Alasan</label><input id="adjNote" name="note" placeholder="mis. Pencairan ke BCA 1234"><span class="err"></span></div>
          </div>
          <small style="color:var(--muted);display:block;margin:-6px 0 12px">Angka negatif mengurangi saldo (mis. setelah Anda mentransfer pencairan ke pelanggan). Tercatat di mutasi & log audit.</small>
          <button type="submit" class="btn-primary btn-sm">Simpan Penyesuaian</button>
        </form>`}
        <h4 style="font-family:var(--font-head);margin:0 0 8px">Mutasi</h4>
        ${d.history.length ? `<div class="table-wrap"><table class="tx"><thead><tr><th>Tanggal</th><th>Keterangan</th><th class="num">Nominal</th><th class="num">Saldo</th><th>Oleh</th></tr></thead>
          <tbody>${d.history.map((h) => `<tr><td>${fmtDate(h.at)}</td><td><b>${esc(h.label)}</b><br><small>${esc(h.note || "")}</small></td>
            <td class="num ${h.amount < 0 ? "neg" : ""}" style="${h.amount > 0 ? "color:#027a48;font-weight:600" : ""}">${h.amount < 0 ? "−" : "+"}${rupiah(Math.abs(h.amount))}</td>
            <td class="num">${rupiah(h.balance_after)}</td><td><small>${esc(h.by || "-")}</small></td></tr>`).join("")}</tbody></table></div>`
          : `<div class="empty" style="padding:20px">Belum ada mutasi.</div>`}`, true);
      const form = $("#adjForm", card);
      if (form) form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        const amount = parseInt(String(form.amount.value).replace(/[^\d-]/g, ""), 10);
        if (!amount) { form.amount.closest(".field").classList.add("invalid"); $(".err", form.amount.closest(".field")).textContent = "Isi nominal"; return; }
        if (!confirm(`${amount > 0 ? "Tambah" : "Kurangi"} saldo ${email} sebesar ${rupiah(Math.abs(amount))}?`)) return;
        try {
          const r = await api("admin/users.php", { method: "POST", body: { email, action: "adjust_balance", amount, note: form.note.value.trim() } });
          toast(r.message, "ok"); ledgerDialog(email, reload); reload && reload();
        } catch (err) {
          const f = err.field && form.elements[err.field];
          if (f) { f.closest(".field").classList.add("invalid"); $(".err", f.closest(".field")).textContent = err.message; } else toast(err.message, "err");
        }
      });
    }

    /* =========================================================
       TAB: SUPPLIER
       ========================================================= */
    async function tabSupplier() {
      view.innerHTML = `<div class="empty">Memuat status supplier…</div>`;
      const d = await api("admin/supplier.php");
      setSupplierChip({ driver: d.driver, testing: d.testing, balance: d.balance, error: d.error });
      const mode = d.driver === "mock" ? "Simulasi (mock)" : d.testing ? "Digiflazz — mode testing" : "Digiflazz — produksi";
      const warn = [];
      if (d.payment === "dummy" && !d.can_simulate) warn.push(`<div class="note bad"><b>Pesanan baru diblokir.</b> Supplier memakai saldo asli tetapi payment gateway masih dummy. Hubungkan payment gateway asli agar pelanggan bisa membayar.</div>`);
      if (d.payment === "dummy" && d.can_simulate) warn.push(`<div class="note">Payment gateway <b>dummy</b> aktif — pembayaran pelanggan masih simulasi.</div>`);
      if (d.error) warn.push(`<div class="note bad"><b>Saldo tidak terbaca:</b> ${esc(d.error)}<br><small>Periksa username/API key Digiflazz dan whitelist IP server.</small></div>`);

      view.innerHTML = `${warn.join("")}
        <div class="tiles">
          <div class="tile"><small>Mode supplier</small><b style="font-size:20px">${esc(mode)}</b><span>Payment: <em>${esc(d.payment)}</em></span></div>
          <div class="tile"><small>Saldo deposit</small><b>${d.balance === null ? "-" : rupiah(d.balance)}</b><span>${d.driver === "mock" ? "Angka simulasi" : "Isi ulang di dashboard Digiflazz"}</span></div>
          <div class="tile"><small>Produk tampil di website</small><b>${num(d.products.live)}</b><span>Gangguan <em>${num(d.products.unavailable)}</em> · nonaktif <em>${num(d.products.inactive)}</em></span></div>
          <div class="tile"><small>Sinkron terakhir</small><b style="font-size:20px">${d.last_sync ? fmtDate(d.last_sync.at) : "Belum pernah"}</b><span>Markup otomatis: <em>Rp ${num(d.markup.flat)} + ${d.markup.percent}%</em></span></div>
        </div>
        <section class="panel" style="margin-bottom:14px">
          <div class="card-head"><h2>Sinkronisasi produk &amp; harga</h2>
            ${d.driver === "mock" ? `<span class="status muted">Tidak tersedia di mode mock</span>` : `<button class="btn-primary btn-sm" id="btnSync">Sinkron Sekarang <span class="arrow">${I.arrow}</span></button>`}</div>
          <p style="margin:0;color:var(--ink-2);font-size:14px">Mengambil price list terbaru dari Digiflazz: harga modal diperbarui, harga jual dihitung ulang (kecuali yang dikunci), produk yang hilang/gangguan disembunyikan. Jadwalkan juga via Cron tiap 6 jam.</p>
          <div id="syncReport">${d.last_sync ? reportHtml(d.last_sync.report) : ""}</div>
        </section>
        <section class="panel">
          <div class="card-head"><h2>Log API terbaru</h2><small style="color:var(--muted)">Detail lengkap per pesanan: klik invoice</small></div>
          ${d.logs.length ? `<div class="table-wrap"><table class="tx admin"><thead><tr><th>Waktu</th><th>Kanal</th><th>Aksi</th><th>Invoice</th><th>HTTP</th><th>Respons</th></tr></thead>
            <tbody>${d.logs.map((l) => `<tr><td>${fmtDate(l.at)}</td><td>${esc(l.channel)}</td><td>${esc(l.action)}</td><td>${l.invoice ? `<a href="#" data-inv="${esc(l.invoice)}" style="color:var(--primary)">${esc(l.invoice)}</a>` : "-"}</td>
              <td>${l.http ?? "-"}</td><td><small style="word-break:break-all" title="${esc(l.response || "")}">${esc((l.response || "").slice(0, 140))}${(l.response || "").length > 140 ? "…" : ""}</small></td></tr>`).join("")}</tbody></table></div>`
            : `<div class="empty" style="padding:24px">Belum ada log.</div>`}
        </section>`;

      $$("[data-inv]", view).forEach((a) => a.addEventListener("click", (ev) => { ev.preventDefault(); orderDetail(a.dataset.inv); }));
      const btn = $("#btnSync");
      if (btn) btn.addEventListener("click", async () => {
        if (!confirm("Jalankan sinkronisasi sekarang? Harga jual produk yang tidak dikunci akan diperbarui.")) return;
        btn.disabled = true; btn.firstChild.textContent = "Menyinkronkan… ";
        try {
          const r = await api("admin/supplier.php", { method: "POST", body: { action: "sync" } });
          toast(r.report.message, "ok");
          $("#syncReport").innerHTML = reportHtml(r.report);
        } catch (err) { toast(err.message, "err"); }
        btn.disabled = false; btn.firstChild.textContent = "Sinkron Sekarang ";
      });
    }

    function reportHtml(r) {
      if (!r) return "";
      if (r.skipped) return `<div class="note info" style="margin:14px 0 0">${esc(r.message)}</div>`;
      const um = Object.entries(r.unmapped || {}).flatMap(([type, rows]) => Object.entries(rows).map(([k, n]) => `<li>[${esc(type)}] ${esc(k)} — ${n} produk</li>`));
      return `<div class="summary" style="margin:14px 0 0">
          <div><span>Prabayar tersinkron</span><b>${num(r.prepaid)}</b></div>
          <div><span>Pascabayar tersinkron</span><b>${num(r.postpaid)}</b></div>
          <div><span>Ditandai tidak tersedia</span><b>${num(r.deactivated)}</b></div>
        </div>
        ${um.length ? `<div class="note" style="margin:12px 0 0"><b>${um.length} brand Digiflazz belum dipetakan</b> (tidak dijual). Petakan lewat Produk &amp; Harga → Pengaturan Brand jika ingin dijual:<ul class="report-list">${um.join("")}</ul></div>` : ""}`;
    }

    /* =========================================================
       ROUTER
       ========================================================= */
    const TABS = {
      ringkasan: ["Ringkasan", tabRingkasan], pesanan: ["Pesanan", () => tabPesanan(false)], refund: ["Refund", () => tabPesanan(true)],
      produk: ["Produk & Harga", tabProduk], digital: ["Produk Digital", tabDigital], pengguna: ["Pengguna", tabPengguna], supplier: ["Supplier", tabSupplier]
    };
    let current = null;
    async function go(tab, push = true) {
      if (!TABS[tab]) tab = "ringkasan";
      current = tab;
      if (push && location.hash !== "#" + tab) history.replaceState(null, "", "#" + tab);
      $$("#adminTabs .tab").forEach((t) => t.classList.toggle("active", t.dataset.tab === tab));
      $("#adminTitle").textContent = TABS[tab][0];
      closeModal();
      try { await TABS[tab][1](); }
      catch (err) {
        if (err.status === 401) { location.replace("login.html?next=admin.html"); return; }
        if (err.code === "mfa_setup_required" || err.code === "mfa_required") { mfaGate(err.code, tab); return; }
        view.innerHTML = `<div class="panel empty">${esc(err.message)}<br><button class="btn-outline btn-sm" style="margin-top:12px" id="retry">Coba lagi</button></div>`;
        $("#retry").addEventListener("click", () => go(tab, false));
      }
    }
    function mfaGate(code, tab) {
      $("#supplierChip").hidden = true;
      $("#adminTitle").textContent = "Verifikasi Dua Langkah";
      const setup = code === "mfa_setup_required";
      view.innerHTML = `<section class="panel mfa-gate">
        <h2>${setup ? "Aktifkan 2FA untuk membuka panel admin" : "Masukkan kode verifikasi"}</h2>
        <p>${setup ? "Panel admin dapat mengubah harga, saldo, dan status pesanan. Demi keamanan, setiap admin wajib memakai verifikasi dua langkah dengan aplikasi authenticator di HP."
                   : "Sesi Anda belum diverifikasi. Masukkan kode 6 digit dari aplikasi authenticator."}</p>
        <div id="mfaBox"></div></section>`;
      const done = () => go(tab, false);
      if (setup) G.twofa.setup($("#mfaBox"), done); else G.twofa.verifyForm($("#mfaBox"), done);
    }

    $("#adminTabs").addEventListener("click", (ev) => { const b = ev.target.closest("[data-tab]"); if (b) go(b.dataset.tab); });
    window.addEventListener("hashchange", () => { const t = location.hash.slice(1); if (t !== current) go(t, false); });

    // Muat saldo & jumlah refund untuk header, lalu buka tab dari URL
    if (location.hash.slice(1) !== "ringkasan" && location.hash) {
      api("admin/dashboard.php").then((d) => { setSupplierChip(d.supplier); setRefundCount(d.alerts.refund_pending); setDigitalCount(d.alerts.digital_waiting); }).catch(() => {});
    }
    go(location.hash.slice(1) || "ringkasan", false);
  });
})();
