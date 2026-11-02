/* =========================================================
   GDK Commerce — Toko Produk Digital
   Daftar produk (toko.html), detail & checkout (produk-digital.html),
   produk pilihan di beranda.
   ========================================================= */
(function () {
  "use strict";

  const CAT_STYLE = {
    game: ["#7c3aed", "#c084fc"], plugin: ["#0f766e", "#2dd4bf"], software: ["#0058c9", "#60b1ff"],
    "website-service": ["#c2410c", "#fb923c"], ebook: ["#be123c", "#fb7185"]
  };
  const CAT_ICON = { game: "game", plugin: "spark", software: "code", "website-service": "globe", ebook: "book" };
  const DELIVERY = {
    download: ["download", "Unduh instan setelah bayar"],
    license: ["key", "Kode lisensi instan setelah bayar"],
    manual: ["clock", "Dikirim admin (maks. 1×24 jam)"]
  };

  document.addEventListener("gdk:ready", () => {
    const G = window.GDK;
    const { api, esc, rupiah, icons: I, $, $$, toast } = G;

    /** Sampul: gambar produk, atau sampul otomatis bergaya per kategori. */
    const cover = (p, big = false) => {
      // Gambar ditampilkan utuh (tidak terpotong); latar buram dari gambar yang sama mengisi sisa ruang
      if (p.image) return `<div class="dg-img"><img class="bg" src="${esc(p.image)}" alt="" aria-hidden="true" loading="lazy"><img class="fg" src="${esc(p.image)}" alt="${esc(p.name)}" loading="lazy"></div>`;
      const [a, b] = CAT_STYLE[p.category] || ["#0058c9", "#60b1ff"];
      return `<div class="dg-cover-gen" style="--c1:${a};--c2:${b}">
        <span class="ic">${I[CAT_ICON[p.category]] || I.spark}</span>
        <b>${esc(p.name.replace(/^(GAME|Game|Plugin|Software|WEBSITE|E-Book)[\s-]+/i, ""))}</b>
        ${big ? `<small>${esc(p.category_name || "")}</small>` : ""}</div>`;
    };
    const priceHtml = (p) => `${p.price_strike ? `<s>${rupiah(p.price_strike)}</s>` : ""}<b>${rupiah(p.price)}</b>`;
    const badges = (p) => {
      const out = [];
      if (p.price_strike) out.push(`<span class="dg-badge sale">-${Math.round((1 - p.price / p.price_strike) * 100)}%</span>`);
      if (p.stock === 0) out.push(`<span class="dg-badge out">Stok habis</span>`);
      else if (p.featured) out.push(`<span class="dg-badge">Pilihan</span>`);
      return out.join("");
    };
    const card = (p, i = 0) => `
      <a class="dg-card" href="produk-digital.html?p=${encodeURIComponent(p.slug)}" style="animation-delay:${Math.min(i, 12) * 40}ms">
        <div class="dg-cover">${cover(p)}<div class="dg-badges">${badges(p)}</div></div>
        <div class="dg-body">
          <span class="dg-cat">${esc(p.category_name || "")}</span>
          <h3>${esc(p.name)}</h3>
          ${p.store ? `<span class="dg-store">${I.shield}${esc(p.store)}</span>` : ""}
          <div class="dg-price">${priceHtml(p)}</div>
        </div>
      </a>`;
    const skeleton = (n) => Array.from({ length: n }, () => `<div class="dg-card skeleton"><div class="dg-cover"></div><div class="dg-body"><span class="dg-cat">&nbsp;</span><h3>&nbsp;</h3><div class="dg-price"><b>&nbsp;</b></div></div></div>`).join("");

    /** Deskripsi: "## Judul" → subjudul, "- poin" → daftar, sisanya paragraf. */
    const renderDesc = (txt) => {
      if (!txt) return "<p style='color:var(--muted)'>Belum ada deskripsi.</p>";
      let html = "", list = false;
      txt.split(/\r?\n/).forEach((line) => {
        const l = line.trim();
        if (/^-\s+/.test(l)) { if (!list) { html += "<ul>"; list = true; } html += `<li>${esc(l.replace(/^-\s+/, ""))}</li>`; return; }
        if (list) { html += "</ul>"; list = false; }
        if (/^##\s+/.test(l)) html += `<h3>${esc(l.replace(/^##\s+/, ""))}</h3>`;
        else if (l) html += `<p>${esc(l)}</p>`;
      });
      return html + (list ? "</ul>" : "");
    };

    /* ---------------- Beranda: produk pilihan ---------------- */
    const home = $("#homeDigital");
    if (home) {
      home.innerHTML = skeleton(4);
      api("digital.php?featured=1&limit=8").then((d) => {
        home.innerHTML = d.products.length ? d.products.map(card).join("") : "";
        if (!d.products.length) home.closest("section").hidden = true;
      }).catch(() => { home.closest("section").hidden = true; });
    }

    /* ---------------- Daftar produk (toko.html) ---------------- */
    const grid = $("#dgGrid");
    if (grid) {
      const params = new URLSearchParams(location.search);
      const st = { category: params.get("cat") || "all", q: params.get("q") || "", sort: params.get("sort") || "" };
      const tabs = $("#dgTabs"), search = $("#dgSearch"), sort = $("#dgSort");
      search.value = st.q; sort.value = st.sort;
      let reqId = 0;
      const load = async () => {
        const my = ++reqId;
        grid.style.opacity = grid.children.length ? ".55" : "";
        if (!grid.children.length) grid.innerHTML = skeleton(8);
        let d;
        try { d = await api("digital.php?" + new URLSearchParams({ category: st.category, q: st.q, sort: st.sort })); }
        catch (e) { grid.innerHTML = `<div class="empty">${esc(e.message)}</div>`; grid.style.opacity = ""; return; }
        if (my !== reqId) return;
        const total = d.categories.reduce((a, c) => a + +c.total, 0);
        tabs.innerHTML = [{ id: "all", name: "Semua", total }].concat(d.categories).map((c) =>
          `<button class="tab ${c.id === st.category ? "active" : ""}" data-cat="${esc(c.id)}">${esc(c.name)} <small>${c.total}</small></button>`).join("");
        $("#dgCount").textContent = `${d.products.length} produk`;
        grid.innerHTML = d.products.length ? d.products.map(card).join("") : `<div class="empty">Produk tidak ditemukan. Coba kata kunci lain.</div>`;
        grid.style.opacity = "";
        const u = new URL(location.href);
        ["cat", "q", "sort"].forEach((k) => u.searchParams.delete(k));
        if (st.category !== "all") u.searchParams.set("cat", st.category);
        if (st.q) u.searchParams.set("q", st.q);
        if (st.sort) u.searchParams.set("sort", st.sort);
        history.replaceState(null, "", u);
      };
      tabs.addEventListener("click", (e) => { const b = e.target.closest("[data-cat]"); if (b) { st.category = b.dataset.cat; load(); } });
      let t; search.addEventListener("input", () => { clearTimeout(t); t = setTimeout(() => { st.q = search.value.trim(); load(); }, 300); });
      sort.addEventListener("change", () => { st.sort = sort.value; load(); });
      load();
    }

    /* ---------------- Detail & checkout (produk-digital.html) ---------------- */
    const detail = $("#dgDetail");
    if (detail) {
      const slug = new URLSearchParams(location.search).get("p");
      (async () => {
        let p, payments;
        try {
          [p, payments] = await Promise.all([
            api("digital.php?slug=" + encodeURIComponent(slug || "")).then((d) => d.product),
            api("catalog.php").then((d) => d.payments)
          ]);
        } catch (e) {
          detail.innerHTML = `<div class="empty">${esc(e.message)}<br><a href="toko.html" class="btn-outline" style="margin-top:14px">Kembali ke toko</a></div>`;
          return;
        }
        document.title = p.name + " — GDK Commerce";
        const user = G.auth.user;
        const [dIcon, dText] = DELIVERY[p.delivery] || DELIVERY.manual;
        const out = p.stock === 0;
        const meta = [["Kategori", p.category_name], ["Platform", p.platform], ["Lisensi", p.license_type], ["Versi", p.version], ["Terjual", p.sold ? p.sold + "×" : null]]
          .filter(([, v]) => v);

        detail.innerHTML = `
          <nav class="crumbs"><a href="produk.html">Produk</a><span>›</span><a href="toko.html">Produk Digital</a><span>›</span><a href="toko.html?cat=${esc(p.category)}">${esc(p.category_name)}</a></nav>
          <div class="dg-detail">
            <div class="dg-gallery reveal">${cover(p, true)}<div class="dg-badges">${badges(p)}</div></div>
            <div class="dg-info reveal d1">
              <span class="dg-cat">${esc(p.category_name)}</span>
              <h1>${esc(p.name)}</h1>
              ${p.store ? `<span class="dg-store">${I.shield}${esc(p.store)}</span>` : ""}
              <div class="dg-price big">${priceHtml(p)}</div>
              ${p.short ? `<p class="lead" style="font-size:16px">${esc(p.short)}</p>` : ""}
              <div class="dg-delivery">${I[dIcon]}<span>${dText}</span></div>
              ${meta.length ? `<div class="dg-meta">${meta.map(([k, v]) => `<div><small>${k}</small><b>${esc(v)}</b></div>`).join("")}</div>` : ""}

              <form class="dg-buy panel" id="dgBuy" novalidate>
                ${p.buyer_note_label ? `<div class="field" id="fNote"><label for="dgNote">${esc(p.buyer_note_label)}${p.buyer_note_required ? "" : " <small style='color:var(--muted)'>(opsional)</small>"}</label>
                  <input id="dgNote" maxlength="500" placeholder="${esc(p.buyer_note_label)}"><span class="err">Wajib diisi</span></div>` : ""}
                <div class="field"><label>Metode Pembayaran</label>
                  <div class="pay-grid" id="dgPay">${payments.map((m) => m.kind === "balance"
                    ? `<button type="button" class="opt opt-balance" data-pay="${esc(m.id)}" ${user && (user.balance || 0) >= p.price ? "" : "disabled"}><i style="background:${esc(m.color)}">GDK</i><span>${esc(m.name)}<small>${user ? rupiah(user.balance || 0) + ((user.balance || 0) < p.price ? " · kurang" : "") : "Masuk dulu"}</small></span></button>`
                    : `<button type="button" class="opt" data-pay="${esc(m.id)}"><i style="background:${esc(m.color)}">${esc(m.name.replace("VA ", "").slice(0, 4))}</i>${esc(m.name)}</button>`).join("")}</div>
                </div>
                <div class="summary" id="dgSum"></div>
                ${out ? `<button class="btn-primary block" disabled>Stok habis</button>`
                  : user ? `<button type="submit" class="btn-primary block">Beli Sekarang <span class="arrow">${I.arrow}</span></button>`
                  : `<a class="btn-primary block" href="login.html?next=${encodeURIComponent("produk-digital.html?p=" + p.slug)}">Masuk untuk Membeli <span class="arrow">${I.arrow}</span></a>`}
              </form>
            </div>
          </div>
          <section class="panel dg-desc reveal"><h2>Deskripsi Produk</h2>${renderDesc(p.description)}</section>
          ${p.related.length ? `<section class="dg-related"><h2 class="h2" style="font-size:28px">Produk terkait</h2><div class="dg-grid">${p.related.map(card).join("")}</div></section>` : ""}`;

        let pay = null;
        const sum = () => {
          const fee = pay ? (pay.fee < 1 ? Math.ceil(p.price * pay.fee) : pay.fee) : 0;
          $("#dgSum").innerHTML = `<div><span>${esc(p.name)}</span><span>${rupiah(p.price)}</span></div>
            <div><span>Biaya pembayaran${pay ? " (" + esc(pay.name) + ")" : ""}</span><span>${pay ? rupiah(fee) : "-"}</span></div>
            <div class="total"><span>Total Bayar</span><b>${rupiah(p.price + fee)}</b></div>`;
        };
        sum();
        $("#dgPay").addEventListener("click", (e) => {
          const b = e.target.closest(".opt"); if (!b || b.disabled) return;
          $$("#dgPay .opt").forEach((o) => o.classList.toggle("active", o === b));
          pay = payments.find((m) => m.id === b.dataset.pay); sum();
        });
        const note = $("#dgNote");
        if (note) note.addEventListener("input", () => $("#fNote").classList.remove("invalid"));
        $("#dgBuy").addEventListener("submit", async (e) => {
          e.preventDefault();
          if (!G.auth.user) { location.href = "login.html?next=" + encodeURIComponent("produk-digital.html?p=" + p.slug); return; }
          if (note && p.buyer_note_required && note.value.trim().length < 3) { $("#fNote").classList.add("invalid"); note.focus(); return; }
          if (!pay) return toast("Pilih metode pembayaran.", "err");
          const btn = $("button[type=submit]", e.target); btn.disabled = true;
          try {
            const d = await api("orders.php", { method: "POST", body: { digital: p.slug, payment: pay.id, buyer_note: note ? note.value.trim() : undefined } });
            location.href = "pembayaran.html?invoice=" + encodeURIComponent(d.order.invoice);
          } catch (err) {
            if (err.field === "buyer_note" && note) { $(".err", $("#fNote")).textContent = err.message; $("#fNote").classList.add("invalid"); note.focus(); }
            else toast(err.message, "err");
            btn.disabled = false;
          }
        });
        requestAnimationFrame(() => $$(".reveal", detail).forEach((el) => el.classList.add("in")));
      })();
    }
  });
})();
