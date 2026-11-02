/* =========================================================
   GDK Commerce — App Core
   Header/Footer, Auth via API PHP, Toast, Animasi,
   Katalog, Checkout, Pembayaran, Akun.
   ========================================================= */
(function () {
  "use strict";

  /* ---------- Konfigurasi ---------- */
  const SITE = {
    name: "GDK Commerce",
    logo: "assets/img/logo.png",
    logoFallback: "assets/img/logo.svg",
    logoFull: "assets/img/logo-full.png",
    address: "Menara 165 Lantai 14 Unit E, Jl. TB Simatupang Kav. 1, Cilandak Timur, Pasar Minggu, DKI Jakarta",
    email: "generasiduakarya.gdk@gmail.com",
    phone: "087751148827",
    phoneIntl: "6287751148827"
  };
  window.GDK_SITE = SITE;

  /* ---------- Ikon (inline SVG) ---------- */
  const I = {
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    star: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M11 18h2"/></svg>',
    wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M2.5 9a14 14 0 0119 0M5.5 12.5a9.5 9.5 0 0113 0M8.7 16a5 5 0 016.6 0"/><circle cx="12" cy="19.3" r="1" fill="currentColor"/></svg>',
    bolt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M13 2.5L4.5 13.5H11l-1 8 8.5-11H12z"/></svg>',
    game: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10a5 5 0 015 5v1.5a3.5 3.5 0 01-6.3 2.1L14.5 14h-5l-1.2 1.6A3.5 3.5 0 012 13.5V12a5 5 0 015-5z"/><path d="M7.5 10v3M6 11.5h3"/><circle cx="16" cy="10.5" r=".6" fill="currentColor"/><circle cx="17.5" cy="12.5" r=".6" fill="currentColor"/></svg>',
    wallet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V5.5A1.5 1.5 0 0017.5 4h-12A2.5 2.5 0 003 6.5v11A2.5 2.5 0 005.5 20h13a1.5 1.5 0 001.5-1.5V9a1.5 1.5 0 00-1.5-1.5H5.5"/><path d="M16 13.5h.01"/></svg>',
    receipt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>',
    play: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="3.5"/><path d="M10 9l5 3-5 3z" fill="currentColor"/></svg>',
    shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M12 2.5l8 3v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10v-6z"/><path d="M8.5 12l2.5 2.5 4.5-5" stroke-linecap="round"/></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    tag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M3 12V4a1 1 0 011-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.4" fill="currentColor"/></svg>',
    headset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0116 0v2"/><rect x="2.5" y="13" width="4.5" height="7" rx="2"/><rect x="17" y="13" width="4.5" height="7" rx="2"/><path d="M19 20a3 3 0 01-3 2h-3"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="3"/><path d="M3 7l9 6 9-6"/></svg>',
    call: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h3.5l1.8 4.6-2.3 1.4a11 11 0 006.5 6.5l1.4-2.3 4.6 1.8V19a2 2 0 01-2 2A17 17 0 013 5.5a2 2 0 012-2z"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><path d="M16 4.6a3.5 3.5 0 010 6.8M18 14a6.5 6.5 0 013.5 6"/></svg>',
    spark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M12 2.5l2.2 6.3 6.3 2.2-6.3 2.2L12 19.5l-2.2-6.3L3.5 11l6.3-2.2z"/></svg>',
    code: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="19" height="16" rx="3"/><path d="M9 10l-2.5 2.5L9 15M15 10l2.5 2.5L15 15M13 9l-2 7"/></svg>',
    globe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>',
    book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 012-2h13v15H6a2 2 0 00-2 2z"/><path d="M4 20a2 2 0 002 2h13v-4M8 7h7"/></svg>',
    download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"/></svg>',
    key: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4.5"/><path d="M11.2 11.8L20 3M16 7l3 3M14 9l2 2"/></svg>',
    logout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3M10 16l4-4-4-4M14 12H4"/></svg>'
  };
  window.GDK_ICONS = I;

  /* ---------- Util ---------- */
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const rupiah = (n) => "Rp " + Math.round(n).toLocaleString("id-ID");
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } },
    del(k) { try { localStorage.removeItem(k); } catch (e) {} }
  };
  const params = new URLSearchParams(location.search);
  const currentPage = document.body.dataset.page || "";

  /* ---------- Toast ---------- */
  function toast(msg, type = "") {
    let stack = $(".toast-stack");
    if (!stack) { stack = document.createElement("div"); stack.className = "toast-stack"; stack.setAttribute("aria-live", "polite"); document.body.appendChild(stack); }
    const t = document.createElement("div");
    t.className = "toast " + type;
    t.textContent = msg;
    stack.appendChild(t);
    setTimeout(() => { t.classList.add("out"); setTimeout(() => t.remove(), 300); }, 3200);
  }
  window.GDK_TOAST = toast;

  /* =========================================================
     API (backend PHP) + AUTH
     ========================================================= */
  const API_BASE = "api/";
  let CSRF = "";

  async function api(path, { method = "GET", body } = {}) {
    const opt = { method, credentials: "same-origin", headers: { Accept: "application/json" } };
    if (method !== "GET") {
      opt.headers["X-CSRF-Token"] = CSRF;
      if (body instanceof FormData) opt.body = body; // upload file: browser mengatur Content-Type sendiri
      else { opt.headers["Content-Type"] = "application/json"; opt.body = JSON.stringify(body || {}); }
    }
    let res, data;
    try {
      res = await fetch(API_BASE + path, opt);
      data = await res.json();
    } catch (e) {
      throw Object.assign(new Error("Tidak dapat terhubung ke server. Periksa koneksi Anda."), { status: 0 });
    }
    if (!res.ok || !data.ok) {
      if (res.status === 419) await Auth.refresh(); // token CSRF diperbarui untuk percobaan berikutnya
      throw Object.assign(new Error(data.error || "Terjadi kesalahan."), { status: res.status, field: data.field, code: data.code, data });
    }
    if (data.csrf) CSRF = data.csrf;
    return data;
  }
  window.GDK_API = api;

  const Auth = {
    user: null,
    ready: false,
    async refresh() {
      try {
        const d = await api("auth/me.php");
        this.user = d.user; CSRF = d.csrf;
      } catch (e) { this.user = null; }
      this.ready = true;
      return this.user;
    },
    async register(data) { const d = await api("auth/register.php", { method: "POST", body: data }); this.user = d.user; },
    /** @returns {Promise<boolean>} true bila perlu kode 2FA */
    async login(email, password) { const d = await api("auth/login.php", { method: "POST", body: { email, password } }); if (d.twofa_required) return true; this.user = d.user; return false; },
    async verify2fa(code) { const d = await api("auth/2fa-verify.php", { method: "POST", body: { code } }); this.user = d.user; return d.user; },
    async logout() { await api("auth/logout.php", { method: "POST" }); this.user = null; }
  };
  window.GDK_AUTH = Auth;

  async function doLogout() {
    try { await Auth.logout(); } catch (e) { /* tetap arahkan ulang */ }
    toast("Anda telah keluar.", "ok");
    setTimeout(() => {
      if (["akun", "pembayaran"].includes(currentPage)) location.href = "index.html";
      else location.reload();
    }, 600);
  }

  /* =========================================================
     HEADER & FOOTER
     ========================================================= */
  const NAV = [
    { href: "index.html", label: "Beranda", page: "home" },
    { href: "produk.html", label: "Produk", page: "produk" },
    { href: "tentang.html", label: "Tentang Kami", page: "tentang" },
    { href: "kontak.html", label: "Kontak Kami", page: "kontak" }
  ];

  function logoImg(size) {
    return `<img src="${SITE.logo}" alt="${SITE.name}" width="${size}" height="${size}" onerror="this.onerror=null;this.src='${SITE.logoFallback}'">`;
  }

  function renderHeader() {
    const mount = $("#site-header");
    if (!mount) return;
    const user = Auth.user;
    const navItems = user && user.role === "admin" ? NAV.concat({ href: "admin.html", label: "Admin", page: "admin" }) : NAV;
    const isActive = (n) => n.page === currentPage || (n.page === "produk" && ["toko", "produk-digital"].includes(currentPage));
    const links = navItems.map((n) => `<li><a href="${n.href}" class="${isActive(n) ? "active" : ""}">${n.label}</a></li>`).join("");
    const mLinks = navItems.map((n) => `<a href="${n.href}" class="${isActive(n) ? "active" : ""}">${n.label}</a>`).join("");
    const initial = user ? esc(user.name.charAt(0).toUpperCase()) : "";
    const next = encodeURIComponent(location.pathname.split("/").pop() + location.search || "index.html");

    const actions = user
      ? `<a href="akun.html" class="user-chip desktop-only" title="Akun saya"><span class="avatar">${initial}</span>${esc(user.name.split(" ")[0])}</a>
         <button class="btn-glass desktop-only" data-logout>Keluar <span class="arrow">${I.logout}</span></button>`
      : `<a href="login.html?next=${next}" class="btn-glass desktop-only">Masuk <span class="arrow">${I.arrow}</span></a>`;

    const mActions = user
      ? `<a href="akun.html">Akun Saya (${esc(user.name.split(" ")[0])})</a><button class="link" data-logout>Keluar</button>`
      : `<a href="login.html?next=${next}">Masuk</a><a href="login.html?mode=daftar&next=${next}">Daftar Akun</a>`;

    mount.outerHTML = `
      <div class="nav-shell" id="navShell">
        <nav class="navbar" aria-label="Navigasi utama">
          <a href="index.html" class="brand">${logoImg(34)}<span>GDK<b> Commerce</b></span></a>
          <ul class="nav-links">${links}</ul>
          <div class="nav-actions">
            ${actions}
            <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span></button>
          </div>
        </nav>
        <div class="mobile-menu" role="menu">${mLinks}<hr>${mActions}</div>
      </div>`;

    const shell = $("#navShell");
    const toggle = $(".nav-toggle", shell);
    toggle.addEventListener("click", () => {
      const open = shell.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", open);
    });
    document.addEventListener("click", (e) => { if (!shell.contains(e.target)) { shell.classList.remove("nav-open"); toggle.setAttribute("aria-expanded", "false"); } });
    $$("[data-logout]", shell).forEach((b) => b.addEventListener("click", doLogout));

    const nav = $(".navbar", shell);
    const onScroll = () => nav.classList.toggle("is-scrolled", window.scrollY > 20);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  function renderFooter() {
    const mount = $("#site-footer");
    if (!mount) return;
    const year = new Date().getFullYear();
    mount.outerHTML = `
      <footer class="site-footer">
        <div class="container">
          <div class="footer-grid">
            <div class="footer-about">
              <a href="index.html" class="footer-logo"><img src="${SITE.logoFull}" alt="GDK — Generasi Dua Karya" width="473" height="276"></a>
              <p>Platform PPOB, top up voucher game, dan produk digital terpercaya. Transaksi cepat, harga bersaing, layanan 24/7.</p>
            </div>
            <div>
              <h4>Navigasi</h4>
              <ul>${NAV.map((n) => `<li><a href="${n.href}">${n.label}</a></li>`).join("")}<li><a href="akun.html">Akun Saya</a></li></ul>
            </div>
            <div>
              <h4>Kebijakan</h4>
              <ul>
                <li><a href="kebijakan-privasi.html">Kebijakan Privasi</a></li>
                <li><a href="syarat-ketentuan.html">Syarat &amp; Ketentuan</a></li>
                <li><a href="kontak.html#faq">FAQ</a></li>
              </ul>
            </div>
            <div>
              <h4>Hubungi Kami</h4>
              <ul class="footer-contact">
                <li>${I.pin}<span>${SITE.address}</span></li>
                <li>${I.mail}<a href="mailto:${SITE.email}">${SITE.email}</a></li>
                <li>${I.call}<a href="tel:+${SITE.phoneIntl}">${SITE.phone}</a></li>
              </ul>
            </div>
          </div>
          <div class="footer-bottom">
            <span>&copy; ${year} ${SITE.name}. Seluruh hak cipta dilindungi.</span>
            <nav><a href="kebijakan-privasi.html">Kebijakan Privasi</a><a href="syarat-ketentuan.html">Syarat &amp; Ketentuan</a></nav>
          </div>
        </div>
      </footer>`;
  }

  /* ---------- Reveal on scroll ---------- */
  function initReveal() {
    const els = $$(".reveal");
    if (!("IntersectionObserver" in window)) { els.forEach((e) => e.classList.add("in")); return; }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); } });
    }, { threshold: 0, rootMargin: "0px 0px -40px 0px" }); // threshold 0: elemen sangat tinggi tetap muncul
    els.forEach((e) => io.observe(e));
  }

  /* ---------- Orb video: fallback untuk Safari/iOS (WebM alpha tidak didukung) ---------- */
  function initOrb() {
    const wrap = $(".orb-wrap");
    if (!wrap) return;
    const v = $("video", wrap);
    const ua = navigator.userAgent;
    const isSafari = /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(ua) || /iPad|iPhone|iPod/.test(ua);
    const canWebm = v && v.canPlayType('video/webm; codecs="vp9"');
    if (!v || isSafari || !canWebm) { wrap.classList.add("use-fallback"); if (v) v.removeAttribute("src"), v.load(); return; }
    v.addEventListener("error", () => wrap.classList.add("use-fallback"));
    const p = v.play(); if (p && p.catch) p.catch(() => {});
  }

  /* ---------- Counter animasi ---------- */
  function initCounters() {
    const els = $$("[data-count]");
    if (!els.length) return;
    const run = (el) => {
      const target = parseFloat(el.dataset.count); const suffix = el.dataset.suffix || ""; const dec = (el.dataset.count.split(".")[1] || "").length;
      const start = performance.now(); const dur = 1600;
      const step = (now) => {
        const p = Math.min((now - start) / dur, 1); const e = 1 - Math.pow(1 - p, 3);
        el.textContent = (target * e).toLocaleString("id-ID", { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    const io = new IntersectionObserver((en) => en.forEach((x) => { if (x.isIntersecting) { run(x.target); io.unobserve(x.target); } }), { threshold: 0.5 });
    els.forEach((e) => io.observe(e));
  }

  /* =========================================================
     KATALOG PRODUK (dari API)
     ========================================================= */
  let CATALOG = null;
  async function loadCatalog() {
    if (CATALOG) return CATALOG;
    const d = await api("catalog.php");
    CATALOG = d;
    window.GDK_CATEGORIES = d.categories; window.GDK_PRODUCTS = d.products; window.GDK_PAYMENTS = d.payments;
    return d;
  }
  const findProduct = (id) => (CATALOG ? CATALOG.products : []).find((p) => p.id === id);
  const catName = (id) => ((CATALOG ? CATALOG.categories : []).find((c) => c.id === id) || {}).name || "";
  const minPrice = (p) => (p.type === "postpaid" ? null : Math.min(...p.items.map((i) => i.price)));

  function productCard(p, i = 0) {
    const mp = minPrice(p);
    return `
      <button class="prod-card" data-product="${esc(p.id)}" style="animation-delay:${Math.min(i, 12) * 40}ms">
        ${p.hot ? '<span class="badge-hot">Terlaris</span>' : ""}
        <span class="prod-logo" style="background:${esc(p.color)}">${esc(p.mark)}</span>
        <div><h3>${esc(p.name)}</h3><span class="cat">${esc(catName(p.cat))}</span></div>
        <div class="price">${mp === null ? "Bayar tagihan<b>Cek tagihan</b>" : "Mulai dari<b>" + rupiah(mp) + "</b>"}</div>
      </button>`;
  }
  const skeleton = (n) => Array.from({ length: n }, () => `<div class="prod-card skeleton"><span class="prod-logo"></span><div><h3>&nbsp;</h3><span class="cat">&nbsp;</span></div><div class="price">&nbsp;<b>&nbsp;</b></div></div>`).join("");
  const loadError = (e) => `<div class="empty">${esc(e.message)}<br><button class="btn-outline" style="margin-top:14px" onclick="location.reload()">Muat ulang</button></div>`;

  function bindProductClicks(root) {
    root.addEventListener("click", (e) => {
      const card = e.target.closest("[data-product]");
      if (card) Checkout.open(card.dataset.product);
    });
  }

  async function initProductPage() {
    const grid = $("#prodGrid");
    if (!grid) return;
    const tabs = $("#catTabs");
    const search = $("#prodSearch");
    grid.innerHTML = skeleton(10);
    try { await loadCatalog(); } catch (e) { grid.innerHTML = loadError(e); return; }

    let cat = params.get("cat") || "all";
    if (!CATALOG.categories.some((c) => c.id === cat)) cat = "all";
    tabs.innerHTML = CATALOG.categories.map((c) => `<button class="tab ${c.id === cat ? "active" : ""}" data-cat="${esc(c.id)}">${esc(c.name)}</button>`).join("");

    const render = () => {
      const q = search.value.trim().toLowerCase();
      const list = CATALOG.products.filter((p) => (cat === "all" || p.cat === cat) && (!q || p.name.toLowerCase().includes(q) || catName(p.cat).toLowerCase().includes(q)));
      grid.innerHTML = list.length ? list.map(productCard).join("") : `<div class="empty">Produk tidak ditemukan. Coba kata kunci lain.</div>`;
    };
    tabs.addEventListener("click", (e) => {
      const b = e.target.closest("[data-cat]"); if (!b) return;
      cat = b.dataset.cat;
      $$(".tab", tabs).forEach((t) => t.classList.toggle("active", t === b));
      const u = new URL(location.href); cat === "all" ? u.searchParams.delete("cat") : u.searchParams.set("cat", cat); history.replaceState(null, "", u);
      render();
    });
    search.addEventListener("input", render);
    bindProductClicks(grid);
    render();

    const pid = params.get("p");
    if (pid && findProduct(pid)) setTimeout(() => Checkout.open(pid), 250);
  }

  async function initHomeProducts() {
    const cats = $("#homeCats"), pop = $("#homePopular");
    if (!cats && !pop) return;
    if (pop) pop.innerHTML = skeleton(5);
    try { await loadCatalog(); } catch (e) { if (pop) pop.innerHTML = loadError(e); return; }
    if (cats) {
      cats.innerHTML = CATALOG.categories.filter((c) => c.id !== "all").slice(0, 6).map((c, i) => `
        <a href="produk.html?cat=${esc(c.id)}" class="cat-card reveal d${(i % 4) + 1}">
          <span class="ic">${I[c.icon] || I.spark}</span>
          <div><h3>${esc(c.name)}</h3><p>${esc(c.desc || "")}</p></div>
        </a>`).join("");
      initReveal();
    }
    if (pop) {
      const P = CATALOG.products;
      pop.innerHTML = P.filter((p) => p.hot).concat(P.filter((p) => !p.hot && p.cat === "game")).slice(0, 10).map(productCard).join("");
      bindProductClicks(pop);
    }
  }

  /* =========================================================
     CHECKOUT (modal) → buat pesanan di server → halaman pembayaran
     ========================================================= */
  const Checkout = {
    el: null, p: null, item: null, pay: null, bill: null, plnName: null,
    ensure() {
      if (this.el) return;
      const m = document.createElement("div");
      m.className = "modal"; m.setAttribute("role", "dialog"); m.setAttribute("aria-modal", "true");
      m.innerHTML = `<div class="modal-card"></div>`;
      document.body.appendChild(m);
      m.addEventListener("click", (e) => { if (e.target === m || e.target.closest("[data-close]")) this.close(); });
      document.addEventListener("keydown", (e) => { if (e.key === "Escape" && m.classList.contains("open")) this.close(); });
      this.el = m;
    },
    open(id) {
      const p = findProduct(id); if (!p) return;
      this.ensure();
      this.p = p; this.item = null; this.pay = null; this.bill = null; this.plnName = null;
      this.renderForm();
      this.el.classList.add("open");
      document.body.style.overflow = "hidden";
    },
    close() {
      if (!this.el) return;
      this.el.classList.remove("open");
      document.body.style.overflow = "";
    },
    fee() {
      if (!this.pay) return 0;
      const base = this.subtotal();
      return this.pay.fee < 1 ? Math.ceil(base * this.pay.fee) : this.pay.fee;
    },
    subtotal() {
      if (this.p.type === "postpaid") return this.bill ? this.bill.amount : 0;
      return this.item ? this.item.price : 0;
    },
    renderForm() {
      const p = this.p; const user = Auth.user;
      const card = $(".modal-card", this.el);
      const numeric = !/email|riot/i.test(p.input.label + p.input.hint);
      let extra;
      if (p.type === "postpaid") {
        extra = `<div class="field"><button type="button" class="btn-outline" id="btnInquiry">Cek Tagihan</button><div id="billInfo"></div></div>`;
      } else {
        extra = (p.checkName ? `<div class="field" style="margin-top:-6px"><button type="button" class="btn-outline" id="btnCheckName" style="padding:9px 16px;font-size:14px">Cek Nama Pelanggan</button><div id="nameInfo"></div></div>` : "") +
          `<div class="field"><label>Pilih Nominal</label><div class="opt-grid" id="optItems">${p.items.map((it, i) => `<button type="button" class="opt" data-i="${i}"><b>${esc(it.name)}</b><span>${rupiah(it.price)}</span></button>`).join("")}</div></div>`;
      }

      card.innerHTML = `
        <div class="modal-head">
          <span class="prod-logo" style="background:${esc(p.color)};width:48px;height:48px;font-size:15px">${esc(p.mark)}</span>
          <div><h3>${esc(p.name)}</h3><small>${esc(catName(p.cat))}${p.type === "postpaid" ? " · Pascabayar" : " · Proses instan"}</small></div>
          <button class="modal-close" data-close aria-label="Tutup">${I.close}</button>
        </div>
        <form id="coForm" novalidate>
          <div class="field" id="fTarget">
            <label for="coTarget">${esc(p.input.label)}</label>
            <input id="coTarget" autocomplete="off" placeholder="${esc(p.input.hint)}" inputmode="${numeric ? "numeric" : "text"}">
            <span class="hint">${esc(p.input.hint)}</span>
            <span class="err">${esc(p.input.error)}</span>
          </div>
          ${extra}
          <div class="field"><label>Metode Pembayaran</label>
            <div class="pay-grid" id="optPay">${CATALOG.payments.map((m) => m.kind === "balance"
              ? `<button type="button" class="opt opt-balance" data-pay="${esc(m.id)}" ${user ? "" : "disabled"}><i style="background:${esc(m.color)}">GDK</i><span>${esc(m.name)}<small>${user ? rupiah(user.balance || 0) : "Masuk dulu"}</small></span></button>`
              : `<button type="button" class="opt" data-pay="${esc(m.id)}"><i style="background:${esc(m.color)}">${esc(m.name.replace("VA ", "").slice(0, 4))}</i>${esc(m.name)}</button>`).join("")}</div>
          </div>
          <div class="summary" id="coSummary"></div>
          ${user
            ? `<button type="submit" class="btn-primary block">Beli Sekarang <span class="arrow">${I.arrow}</span></button>`
            : `<a href="login.html?next=${encodeURIComponent("produk.html?p=" + p.id)}" class="btn-primary block">Masuk untuk Membeli <span class="arrow">${I.arrow}</span></a>
               <p style="text-align:center;font-size:13px;color:var(--muted);margin:12px 0 0">Anda harus masuk terlebih dahulu untuk melakukan transaksi.</p>`}
        </form>`;

      const target = $("#coTarget", card);
      const fT = $("#fTarget", card);
      const setErr = (msg) => { $(".err", fT).textContent = msg || p.input.error; fT.classList.add("invalid"); };
      const validTarget = () => new RegExp(p.input.pattern).test(target.value.trim());
      const needLogin = () => { if (Auth.user) return false; location.href = "login.html?next=" + encodeURIComponent("produk.html?p=" + p.id); return true; };
      target.addEventListener("input", () => {
        fT.classList.remove("invalid");
        if (p.type === "postpaid" && this.bill) { this.bill = null; $("#billInfo", card).innerHTML = ""; this.summary(); }
        if (this.plnName) { this.plnName = null; $("#nameInfo", card).innerHTML = ""; }
      });

      const optItems = $("#optItems", card);
      if (optItems) optItems.addEventListener("click", (e) => {
        const b = e.target.closest(".opt"); if (!b) return;
        $$(".opt", optItems).forEach((o) => o.classList.toggle("active", o === b));
        this.item = p.items[+b.dataset.i]; this.summary();
      });

      const btnName = $("#btnCheckName", card);
      if (btnName) btnName.addEventListener("click", async () => {
        if (needLogin()) return;
        if (!validTarget()) return setErr();
        btnName.disabled = true; btnName.textContent = "Mengecek…";
        try {
          const d = await api("check-name.php", { method: "POST", body: { brand: p.id, customer_no: target.value.trim() } });
          this.plnName = d.name;
          $("#nameInfo", card).innerHTML = `<div class="summary" style="margin:8px 0 0"><div><span>Nama</span><b>${esc(d.name)}</b></div>${d.segment ? `<div><span>Tarif/Daya</span><span>${esc(d.segment)}</span></div>` : ""}</div>`;
        } catch (e) { setErr(e.message); }
        btnName.disabled = false; btnName.textContent = "Cek Ulang Nama";
      });

      const inq = $("#btnInquiry", card);
      if (inq) inq.addEventListener("click", async () => {
        if (needLogin()) return;
        if (!validTarget()) return setErr();
        inq.textContent = "Mengecek…"; inq.disabled = true;
        try {
          const d = await api("inquiry.php", { method: "POST", body: { brand: p.id, customer_no: target.value.trim() } });
          this.bill = d;
          $("#billInfo", card).innerHTML = `<div class="summary" style="margin:6px 0 0">
            <div><span>Nama</span><b>${esc(d.customer_name || "-")}</b></div>
            ${d.period ? `<div><span>Periode</span><span>${esc(d.period)}</span></div>` : ""}
            <div><span>Tagihan (termasuk admin)</span><span>${rupiah(d.bill)}</span></div>
            ${d.admin ? `<div><span>Biaya layanan</span><span>${rupiah(d.admin)}</span></div>` : ""}</div>`;
        } catch (e) { setErr(e.message); }
        inq.textContent = this.bill ? "Cek Ulang" : "Cek Tagihan"; inq.disabled = false;
        this.summary();
      });

      $("#optPay", card).addEventListener("click", (e) => {
        const b = e.target.closest(".opt"); if (!b || b.disabled) return;
        $$("#optPay .opt", card).forEach((o) => o.classList.toggle("active", o === b));
        this.pay = CATALOG.payments.find((m) => m.id === b.dataset.pay); this.summary();
      });

      $("#coForm", card).addEventListener("submit", async (e) => {
        e.preventDefault();
        if (needLogin()) return;
        if (!validTarget()) { setErr(); target.focus(); return; }
        if (p.type === "postpaid" && !this.bill) return toast("Silakan cek tagihan terlebih dahulu.", "err");
        if (p.type !== "postpaid" && !this.item) return toast("Pilih nominal terlebih dahulu.", "err");
        if (!this.pay) return toast("Pilih metode pembayaran.", "err");
        if (this.pay.kind === "balance" && this.subtotal() > (Auth.user.balance || 0)) return toast("Saldo GDK tidak cukup. Pilih metode pembayaran lain.", "err");
        const btn = $("button[type=submit]", card); btn.disabled = true;
        const body = p.type === "postpaid"
          ? { inquiry_id: this.bill.inquiry_id, payment: this.pay.id }
          : { sku: this.item.sku, customer_no: target.value.trim(), payment: this.pay.id, customer_name: this.plnName || undefined };
        try {
          const d = await api("orders.php", { method: "POST", body });
          location.href = "pembayaran.html?invoice=" + encodeURIComponent(d.order.invoice);
        } catch (err) {
          if (err.status === 401) { Auth.user = null; needLogin(); return; }
          if (err.field === "customer_no") setErr(err.message); else toast(err.message, "err");
          if (err.field === "balance" && Auth.user) { Auth.user.balance = err.data.balance; this.renderBalance(); }
          btn.disabled = false;
        }
      });

      this.summary();
    },
    renderBalance() {
      const btn = $("#optPay .opt-balance", this.el); if (!btn || !Auth.user) return;
      const bal = Auth.user.balance || 0; const need = this.subtotal();
      const short = need > 0 && bal < need;
      btn.disabled = short || bal <= 0;
      btn.title = short ? "Saldo tidak cukup" : "";
      $("small", btn).textContent = rupiah(bal) + (short ? " · kurang" : "");
      if (btn.disabled && this.pay && this.pay.kind === "balance") { this.pay = null; btn.classList.remove("active"); }
    },
    summary() {
      const box = $("#coSummary", this.el); if (!box) return;
      this.renderBalance();
      const p = this.p;
      const label = p.type === "postpaid" ? "Total tagihan" : (this.item ? this.item.name : "Nominal");
      const sub = this.subtotal(); const fee = this.fee();
      box.innerHTML = `
        <div><span>${esc(label)}</span><span>${sub ? rupiah(sub) : "-"}</span></div>
        <div><span>Biaya pembayaran${this.pay ? " (" + esc(this.pay.name) + ")" : ""}</span><span>${this.pay && sub ? rupiah(fee) : "-"}</span></div>
        <div class="total"><span>Total Bayar</span><b>${sub ? rupiah(sub + (this.pay ? fee : 0)) : "-"}</b></div>`;
    }
  };
  window.GDK_CHECKOUT = Checkout;

  /* =========================================================
     HALAMAN PEMBAYARAN
     ========================================================= */
  const STATUS_CLS = { success: "ok", unpaid: "wait", processing: "wait", failed: "bad", expired: "bad" };

  async function initPaymentPage() {
    const root = $("#payRoot"); if (!root) return;
    if (!Auth.user) { location.replace("login.html?next=" + encodeURIComponent("pembayaran.html" + location.search)); return; }
    const invoice = params.get("invoice");
    if (!invoice) { root.innerHTML = `<div class="empty">Invoice tidak ditemukan.</div>`; return; }

    let timer = null, countdown = null, lastStatus = null;
    const stop = () => { clearTimeout(timer); clearInterval(countdown); };

    const render = (o) => {
      const pay = o.payment;
      const statusIcon = o.status === "success" ? I.check : o.status === "failed" || o.status === "expired" ? I.close : I.clock;
      const heading = {
        unpaid: "Selesaikan Pembayaran", processing: o.kind === "digital" ? "Pesanan Sedang Disiapkan" : "Pesanan Sedang Diproses", success: "Transaksi Berhasil",
        failed: "Transaksi Gagal", expired: "Pembayaran Kedaluwarsa"
      }[o.status] || o.status_label;
      const sub = {
        unpaid: `Bayar <b>${rupiah(o.total)}</b> melalui <b>${esc(pay.name)}</b> sebelum batas waktu berakhir.`,
        processing: o.kind === "digital"
          ? "Pembayaran diterima. Tim kami sedang menyiapkan produk Anda (maks. 1×24 jam). Produk akan muncul di halaman ini dan di riwayat transaksi."
          : "Pembayaran diterima. Produk sedang dikirim ke tujuan — halaman ini diperbarui otomatis.",
        success: o.kind === "digital" ? "Pembayaran berhasil. Produk Anda sudah siap di bawah ini." : `Produk telah dikirim ke <b>${esc(o.customer_no)}</b>.`,
        failed: `${esc(o.message || "Transaksi tidak dapat diproses oleh penyedia.")}<br>${o.refund_status === "done" && o.refund_method === "saldo"
          ? `Dana <b>${rupiah(o.total)}</b> sudah dikembalikan ke <a href="akun.html#saldo" style="color:var(--primary)">Saldo GDK</a> Anda dan bisa langsung dipakai.`
          : "Dana Anda akan dikembalikan. Tim kami akan menghubungi Anda."}`,
        expired: "Batas waktu pembayaran telah lewat. Silakan buat pesanan baru."
      }[o.status] || "";

      let payBox = "";
      if (o.status === "unpaid") {
        if (pay.code) payBox = `<div class="pay-code"><small>Nomor Virtual Account ${esc(pay.name.replace("VA ", ""))}</small><div><b id="vaCode">${esc(pay.code)}</b><button class="btn-outline copy" data-copy="${esc(pay.code)}">Salin</button></div></div>`;
        else if (pay.qr) payBox = `<div class="pay-code" style="text-align:center"><div id="qrBox" class="qr-box"></div><small>Pindai QR di aplikasi e-wallet / m-banking</small></div>`;
        else if (pay.url) payBox = `<a class="btn-primary block" href="${esc(pay.url)}" target="_blank" rel="noopener">Bayar dengan ${esc(pay.name)} <span class="arrow">${I.arrow}</span></a>`;
        else payBox = `<div class="pay-code"><small>Pembayaran ${esc(pay.name)}</small><div><span>Ikuti instruksi pada aplikasi ${esc(pay.name)}.</span></div></div>`;
        payBox += `<div class="countdown">Bayar dalam <b id="cd">--:--</b></div>`;
      }

      root.innerHTML = `
        <div class="pay-grid-page">
          <div class="panel">
            <div class="pay-status ${STATUS_CLS[o.status] || ""}"><span class="ic">${statusIcon}</span><div><small>${esc(o.invoice)}</small><h2>${heading}</h2></div></div>
            <p class="lead" style="font-size:16px;margin:0 0 20px">${sub}</p>
            ${payBox}
            ${o.kind === "digital" && o.status === "success" ? deliveryHtml(o.delivery) : ""}
            ${o.status === "success" && o.sn && o.kind !== "digital" ? `<div class="pay-code"><small>Serial Number / Token</small><div><b style="font-size:16px;word-break:break-all">${esc(o.sn)}</b><button class="btn-outline copy" data-copy="${esc(o.sn)}">Salin</button></div></div>` : ""}
            ${o.status === "processing" && o.kind !== "digital" ? `<div class="progress"><span></span></div>` : ""}
            ${pay.simulate ? `<div class="sim-box"><b>Mode Demo — Payment Gateway Dummy</b><p>Belum ada gateway asli yang terhubung. Klik tombol di bawah untuk mensimulasikan pelanggan membayar. Pesanan akan langsung diteruskan ke supplier.</p><button class="btn-primary" id="btnSim">Simulasikan Pembayaran <span class="arrow">${I.arrow}</span></button></div>` : ""}
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:22px">
              <a href="akun.html" class="btn-outline">Riwayat Transaksi</a>
              ${["success", "failed", "expired"].includes(o.status) ? `<a href="produk.html" class="btn-outline">Beli Lagi</a>` : ""}
            </div>
          </div>
          <aside class="panel">
            <h2>Rincian Pesanan</h2>
            <div class="kv">
              <div><span>Status</span><span class="status ${STATUS_CLS[o.status] || ""}">${esc(o.status_label)}</span></div>
              <div><span>Produk</span><span>${esc(o.brand)}</span></div>
              <div><span>Item</span><span>${esc(o.item)}</span></div>
              ${o.kind === "digital" ? (o.buyer_note ? `<div><span>Catatan Anda</span><span>${esc(o.buyer_note)}</span></div>` : "") : `<div><span>Tujuan</span><span>${esc(o.customer_no)}</span></div>`}
              ${o.customer_name ? `<div><span>Nama</span><span>${esc(o.customer_name)}</span></div>` : ""}
              <div><span>Harga</span><span>${rupiah(o.price)}</span></div>
              <div><span>Biaya pembayaran</span><span>${rupiah(o.fee)}</span></div>
              <div><span>Metode</span><span>${esc(pay.name || "-")}</span></div>
              <div><span>Dibuat</span><span>${fmtDate(o.created_at)}</span></div>
            </div>
            <div class="summary" style="margin-bottom:0"><div class="total" style="margin:0;border:0;padding:0"><span>Total</span><b>${rupiah(o.total)}</b></div></div>
          </aside>
        </div>`;

      $$("[data-copy]", root).forEach((b) => b.addEventListener("click", () => {
        navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => toast("Disalin ke clipboard.", "ok"));
      }));
      if (pay.qr && $("#qrBox") && window.QRCode) new QRCode($("#qrBox"), { text: pay.qr, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });

      clearInterval(countdown);
      if (o.status === "unpaid" && pay.expired_at) {
        const tick = () => {
          const ms = pay.expired_at - Date.now();
          const el = $("#cd"); if (!el) return;
          if (ms <= 0) { el.textContent = "00:00"; clearInterval(countdown); poll(0); return; }
          const m = Math.floor(ms / 60000), s = Math.floor((ms % 60000) / 1000);
          el.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
        };
        tick(); countdown = setInterval(tick, 1000);
      }

      const sim = $("#btnSim", root);
      if (sim) sim.addEventListener("click", async () => {
        sim.disabled = true; sim.firstChild.textContent = "Memproses… ";
        try {
          const d = await api("payment/simulate.php", { method: "POST", body: { invoice: o.invoice } });
          toast("Pembayaran diterima (simulasi).", "ok");
          update(d.order);
        } catch (e) { toast(e.message, "err"); sim.disabled = false; sim.firstChild.textContent = "Simulasikan Pembayaran "; }
      });
    };

    const update = (o) => {
      if (o.status !== lastStatus) {
        if (lastStatus && o.status === "success") toast("Transaksi berhasil!", "ok");
        if (lastStatus && o.status === "failed") toast("Transaksi gagal diproses.", "err");
        const changed = lastStatus !== null;
        lastStatus = o.status;
        render(o);
        if (changed) window.scrollTo({ top: Math.max(0, root.getBoundingClientRect().top + window.scrollY - 130), behavior: "smooth" });
      }
      clearTimeout(timer);
      if (o.status === "unpaid") timer = setTimeout(() => poll(), 5000);
      else if (o.status === "processing") timer = setTimeout(() => poll(), o.kind === "digital" ? 20000 : 3000);
    };

    const poll = async () => {
      try {
        const d = await api("orders.php?invoice=" + encodeURIComponent(invoice));
        update(d.order);
      } catch (e) {
        if (e.status === 404 || e.status === 401) { stop(); root.innerHTML = `<div class="empty">${esc(e.message)}</div>`; return; }
        clearTimeout(timer); timer = setTimeout(() => poll(), 8000);
      }
    };
    root.innerHTML = `<div class="empty">Memuat pesanan…</div>`;
    poll();
    document.addEventListener("visibilitychange", () => { if (!document.hidden && ["unpaid", "processing"].includes(lastStatus)) poll(); });
  }

  /** Isi produk digital yang diterima pembeli. */
  function deliveryHtml(d) {
    if (!d) return "";
    if (d.error) return `<div class="note-sm">${esc(d.error)}</div>`;
    let h = `<div class="dg-deliver"><h3>${I.check} Produk Anda</h3>`;
    (d.keys || []).forEach((k) => {
      h += `<div class="pay-code"><small>Kode lisensi</small><div><b style="font-size:17px;word-break:break-all">${esc(k)}</b><button class="btn-outline copy" data-copy="${esc(k)}">Salin</button></div></div>`;
    });
    if (d.download) {
      if (d.download.url) h += `<a class="btn-primary" href="${esc(d.download.url)}" download>${I.download} Unduh ${esc(d.download.name || "file")}</a>
        <small class="dg-dl-left">Sisa unduhan: ${d.download.left}×</small>`;
      else if (d.download.external) h += `<a class="btn-primary" href="${esc(d.download.external)}" target="_blank" rel="noopener noreferrer">${I.download} Buka link unduhan</a>`;
    }
    if (d.text) {
      const linked = esc(d.text).replace(/(https:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
      h += `<div class="pay-code"><small>Dari admin</small><div class="dg-text">${linked}</div><div style="margin-top:8px"><button class="btn-outline copy" data-copy="${esc(d.text)}">Salin semua</button></div></div>`;
    }
    if (d.note) h += `<div class="note-sm info">${esc(d.note)}</div>`;
    return h + "</div>";
  }

  const fmtDate = (ms) => ms ? new Date(ms).toLocaleString("id-ID", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "-";

  /* =========================================================
     HALAMAN LOGIN / DAFTAR
     ========================================================= */
  function initAuthPage() {
    const wrap = $("#authCard"); if (!wrap) return;
    const next = params.get("next");
    const safeNext = next && /^[a-z0-9\-]+\.html(\?[^\s#]*)?(#[a-z0-9\-]*)?$/i.test(next) ? next : "akun.html";
    if (Auth.user) { location.replace(safeNext); return; }

    const formL = $("#formLogin"), formR = $("#formRegister");
    const sw = $$(".auth-switch button");
    const setMode = (m) => {
      sw.forEach((b) => b.classList.toggle("active", b.dataset.mode === m));
      formL.hidden = m !== "masuk"; formR.hidden = m !== "daftar";
      $("#authTitle").textContent = m === "masuk" ? "Selamat datang kembali" : "Buat akun baru";
      $("#authSub").textContent = m === "masuk" ? "Masuk untuk melanjutkan transaksi Anda." : "Daftar gratis dan nikmati harga terbaik.";
    };
    sw.forEach((b) => b.addEventListener("click", () => setMode(b.dataset.mode)));
    $$("[data-switch]").forEach((a) => a.addEventListener("click", (e) => { e.preventDefault(); setMode(a.dataset.switch); }));
    setMode(params.get("mode") === "daftar" ? "daftar" : "masuk");

    $$(".pw-wrap button").forEach((b) => b.addEventListener("click", () => {
      const i = b.previousElementSibling; i.type = i.type === "password" ? "text" : "password"; b.textContent = i.type === "password" ? "Lihat" : "Sembunyi";
    }));

    const validate = (form, rules) => {
      let ok = true;
      Object.entries(rules).forEach(([name, fn]) => {
        const input = form.elements[name]; const f = input.closest(".field");
        const bad = !fn(input.value, form); f.classList.toggle("invalid", bad); if (bad && ok) { input.focus(); ok = false; }
      });
      return ok;
    };
    const fieldError = (form, err) => {
      const input = err.field && form.elements[err.field];
      const f = input && input.closest && input.closest(".field");
      if (f) { $(".err", f).textContent = err.message; f.classList.add("invalid"); input.focus(); }
      else toast(err.message, "err");
    };
    const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());

    formL.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!validate(formL, { email: isEmail, password: (v) => v.length >= 1 })) return;
      const btn = $("button[type=submit]", formL); btn.disabled = true;
      try {
        const need2fa = await Auth.login(formL.email.value, formL.password.value);
        if (need2fa) {
          formL.hidden = true; $(".auth-switch", wrap).hidden = true;
          $("#authTitle").textContent = "Verifikasi dua langkah";
          $("#authSub").textContent = "Masukkan kode 6 digit dari aplikasi authenticator Anda.";
          const box = $("#form2fa"); box.hidden = false;
          TwoFA.verifyForm(box, () => { toast("Berhasil masuk. Selamat datang!", "ok"); setTimeout(() => location.replace(safeNext), 400); },
            () => { box.hidden = true; formL.hidden = false; $(".auth-switch", wrap).hidden = false; setMode("masuk"); formL.password.value = ""; btn.disabled = false; });
          return;
        }
        toast("Berhasil masuk. Selamat datang!", "ok"); setTimeout(() => location.replace(safeNext), 500);
      }
      catch (err) { toast(err.message, "err"); btn.disabled = false; }
    });

    formR.addEventListener("submit", async (e) => {
      e.preventDefault();
      const ok = validate(formR, {
        name: (v) => v.trim().length >= 3,
        email: isEmail,
        phone: (v) => /^08[0-9]{8,12}$/.test(v.trim()),
        password: (v) => v.length >= 8 && /[A-Za-z]/.test(v) && /[0-9]/.test(v),
        password2: (v, f) => v === f.password.value && v.length > 0
      });
      if (!ok) return;
      if (!formR.agree.checked) return toast("Anda harus menyetujui Syarat & Ketentuan.", "err");
      const btn = $("button[type=submit]", formR); btn.disabled = true;
      try {
        await Auth.register({ name: formR.name.value, email: formR.email.value, phone: formR.phone.value, password: formR.password.value, password2: formR.password2.value, agree: true });
        toast("Akun berhasil dibuat!", "ok"); setTimeout(() => location.replace(safeNext), 500);
      } catch (err) { fieldError(formR, err); btn.disabled = false; }
    });
    $$("input", wrap).forEach((i) => i.addEventListener("input", () => i.closest(".field") && i.closest(".field").classList.remove("invalid")));
  }

  /* =========================================================
     HALAMAN AKUN
     ========================================================= */
  async function initAccountPage() {
    const root = $("#accRoot"); if (!root) return;
    const user = Auth.user;
    if (!user) { location.replace("login.html?next=akun.html"); return; }
    root.innerHTML = `<div class="empty">Memuat data akun…</div>`;
    let d, w;
    try { [d, w] = await Promise.all([api("orders.php"), api("wallet.php")]); } catch (e) { root.innerHTML = loadError(e); return; }
    const orders = d.orders;
    const refundBadge = (o) => o.refund_status === "done" ? `<br><small class="status ok" style="margin-top:4px">${o.refund_method === "saldo" ? "Refund ke saldo" : "Sudah direfund"}</small>`
      : o.refund_status === "pending" ? `<br><small class="status wait" style="margin-top:4px">Refund diproses</small>` : "";

    root.innerHTML = `
      <div class="acc-grid">
        <aside class="acc-side">
          <div class="panel reveal">
            <div class="profile-head"><span class="avatar">${esc(user.name.charAt(0).toUpperCase())}</span><div><b>${esc(user.name)}</b><small>Member sejak ${new Date(user.created_at).toLocaleDateString("id-ID", { month: "long", year: "numeric" })}</small></div></div>
            <div class="wallet-card" id="saldo">
              <small>Saldo GDK</small><b>${rupiah(w.balance)}</b>
              <span>Refund otomatis masuk ke sini & bisa dipakai bayar tanpa biaya.</span>
            </div>
            <div class="kv">
              <div><span>Email</span><span>${esc(user.email)}</span></div>
              <div><span>No. HP</span><span>${esc(user.phone)}</span></div>
              <div><span>Transaksi berhasil</span><b>${d.stats.success}</b></div>
              <div><span>Total belanja</span><b>${rupiah(d.stats.spent)}</b></div>
            </div>
            <div style="display:grid;gap:10px;margin-top:20px">
              <a href="produk.html" class="btn-primary block">Beli Produk <span class="arrow">${I.arrow}</span></a>
              <button class="btn-outline" data-logout>Keluar</button>
            </div>
          </div>
          <div class="panel reveal d1" id="securityPanel"></div>
        </aside>
        <div class="acc-main">
          <section class="panel reveal d1">
            <h2>Riwayat Transaksi</h2>
            ${orders.length ? `<div class="table-wrap"><table class="tx">
              <thead><tr><th>Invoice</th><th>Produk</th><th>Tujuan</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
              <tbody>${orders.map((o) => `<tr>
                <td><a href="pembayaran.html?invoice=${encodeURIComponent(o.invoice)}" style="color:var(--primary)"><b>${esc(o.invoice)}</b></a><br><small style="color:var(--muted)">${esc(o.payment.name || "")}</small></td>
                <td>${esc(o.brand)}<br><small style="color:var(--muted)">${esc(o.item)}</small></td>
                <td>${esc(o.customer_no)}${o.sn ? `<br><small style="color:var(--muted);word-break:break-all">SN: ${esc(o.sn)}</small>` : ""}</td>
                <td><b>${rupiah(o.total)}</b></td>
                <td><span class="status ${STATUS_CLS[o.status] || ""}">${esc(o.status_label)}</span>${refundBadge(o)}</td>
                <td>${fmtDate(o.created_at)}</td>
              </tr>`).join("")}</tbody></table></div>`
            : `<div class="empty" style="padding:40px 10px">Belum ada transaksi.<br><a href="produk.html" style="color:var(--primary);font-weight:500">Mulai belanja sekarang →</a></div>`}
          </section>
          <section class="panel reveal d2">
            <h2>Mutasi Saldo GDK</h2>
            ${w.history.length ? `<div class="table-wrap"><table class="tx">
              <thead><tr><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Nominal</th><th style="text-align:right">Saldo</th></tr></thead>
              <tbody>${w.history.map((h) => `<tr>
                <td>${fmtDate(h.at)}</td>
                <td><b>${esc(h.label)}</b><br><small style="color:var(--muted)">${esc(h.note || "")}</small></td>
                <td style="text-align:right;white-space:nowrap" class="${h.amount < 0 ? "amt-out" : "amt-in"}">${h.amount < 0 ? "−" : "+"}${rupiah(Math.abs(h.amount))}</td>
                <td style="text-align:right;white-space:nowrap">${rupiah(h.balance_after)}</td></tr>`).join("")}</tbody></table></div>`
            : `<div class="empty" style="padding:30px 10px">Belum ada mutasi saldo.</div>`}
          </section>
        </div>
      </div>`;
    $$("[data-logout]", root).forEach((b) => b.addEventListener("click", doLogout));
    // Tabel panjang: tampilkan 10 baris dulu
    $$(".acc-main table.tx", root).forEach((t) => {
      const rows = $$("tbody tr", t); if (rows.length <= 10) return;
      rows.slice(10).forEach((r) => (r.hidden = true));
      const btn = document.createElement("button");
      btn.className = "btn-outline btn-xs"; btn.style.marginTop = "12px";
      btn.textContent = `Tampilkan semua (${rows.length})`;
      btn.addEventListener("click", () => { rows.forEach((r) => (r.hidden = false)); btn.remove(); });
      t.closest(".table-wrap").after(btn);
    });
    renderSecurity($("#securityPanel"));
    initReveal();
    if (location.hash === "#saldo") setTimeout(() => $("#saldo").scrollIntoView({ behavior: "smooth", block: "center" }), 300);
  }

  function renderSecurity(panel) {
    const u = Auth.user;
    panel.innerHTML = `<h2>Keamanan Akun</h2>
      <div class="sec-row"><span class="ic">${I.shield}</span><div><b>Verifikasi dua langkah (2FA)</b>
        <small>${u.twofa ? `Aktif · ${u.recovery_left} kode cadangan tersisa` : "Lindungi akun dengan kode dari aplikasi authenticator di HP."}</small></div>
        <span class="status ${u.twofa ? "ok" : "muted"}">${u.twofa ? "Aktif" : "Nonaktif"}</span></div>
      ${u.role === "admin" && !u.twofa ? `<div class="note-sm">Wajib untuk admin — panel admin terkunci sampai 2FA aktif.</div>` : ""}
      <button class="btn-outline block-btn" id="btn2fa">${u.twofa ? "Nonaktifkan 2FA" : "Aktifkan 2FA"}</button>`;
    $("#btn2fa", panel).addEventListener("click", () => {
      const card = Dialog.open(`<div class="modal-head"><div><h3>${u.twofa ? "Nonaktifkan 2FA" : "Aktifkan Verifikasi Dua Langkah"}</h3></div><button class="modal-close" data-close aria-label="Tutup">${I.close}</button></div><div id="tfaBox"></div>`);
      const box = $("#tfaBox", card);
      const done = () => { Dialog.close(); renderSecurity(panel); };
      if (u.twofa) TwoFA.disableForm(box, done); else TwoFA.setup(box, done);
    });
  }

  /* =========================================================
     DIALOG SEDERHANA (dipakai halaman akun)
     ========================================================= */
  const Dialog = {
    el: null,
    open(html) {
      if (!this.el) {
        this.el = document.createElement("div");
        this.el.className = "modal"; this.el.setAttribute("role", "dialog"); this.el.setAttribute("aria-modal", "true");
        this.el.innerHTML = `<div class="modal-card"></div>`;
        document.body.appendChild(this.el);
        this.el.addEventListener("click", (e) => { if (e.target === this.el || e.target.closest("[data-close]")) this.close(); });
        document.addEventListener("keydown", (e) => { if (e.key === "Escape" && this.el.classList.contains("open")) this.close(); });
      }
      const card = $(".modal-card", this.el); card.innerHTML = html;
      this.el.classList.add("open"); document.body.style.overflow = "hidden";
      return card;
    },
    close() { if (this.el) { this.el.classList.remove("open"); document.body.style.overflow = ""; } }
  };

  /* =========================================================
     2FA — komponen bersama (akun, login, panel admin)
     ========================================================= */
  const TwoFA = {
    async setup(box, onDone) {
      box.innerHTML = `<div class="empty">Menyiapkan…</div>`;
      let d;
      try { d = await api("auth/2fa-setup.php"); } catch (e) { box.innerHTML = `<div class="empty">${esc(e.message)}</div>`; return; }
      box.innerHTML = `
        <ol class="tfa-steps">
          <li>Pasang aplikasi authenticator di HP: <b>Google Authenticator</b>, <b>Microsoft Authenticator</b>, atau <b>Authy</b>.</li>
          <li>Di aplikasi, pilih <b>Tambah akun → Pindai kode QR</b>, lalu pindai kode di bawah.</li>
          <li>Masukkan 6 digit kode yang muncul di aplikasi beserta kata sandi akun Anda.</li>
        </ol>
        <div class="tfa-qr">
          <div class="qr-box" id="tfaQr"></div>
          <div><small>Tidak bisa memindai? Masukkan kode ini secara manual:</small><code class="tfa-secret">${esc(d.secret)}</code>
            <button type="button" class="btn-outline btn-xs" id="tfaCopy">Salin kode</button></div>
        </div>
        <form id="tfaForm" novalidate>
          <div class="row-2">
            <div class="field"><label for="tfaCode">Kode 6 digit</label><input id="tfaCode" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456"><span class="err">Kode salah</span></div>
            <div class="field"><label for="tfaPass">Kata sandi akun</label><input id="tfaPass" name="password" type="password" autocomplete="current-password"><span class="err">Kata sandi salah</span></div>
          </div>
          <button type="submit" class="btn-primary block">Aktifkan 2FA <span class="arrow">${I.arrow}</span></button>
        </form>`;
      if (window.QRCode) new QRCode($("#tfaQr", box), { text: d.uri, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
      else $("#tfaQr", box).innerHTML = `<small>QR tidak dapat dimuat — gunakan kode manual.</small>`;
      $("#tfaCopy", box).addEventListener("click", () => navigator.clipboard && navigator.clipboard.writeText(d.secret.replace(/\s/g, "")).then(() => toast("Kode disalin.", "ok")));
      const form = $("#tfaForm", box);
      $$("input", form).forEach((i) => i.addEventListener("input", () => i.closest(".field").classList.remove("invalid")));
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const btn = $("button[type=submit]", form); btn.disabled = true;
        try {
          const r = await api("auth/2fa-setup.php", { method: "POST", body: { code: form.code.value.trim(), password: form.password.value } });
          Auth.user = r.user;
          this.showRecovery(box, r.recovery_codes, onDone);
        } catch (err) {
          const f = err.field && form.elements[err.field];
          if (f) { $(".err", f.closest(".field")).textContent = err.message; f.closest(".field").classList.add("invalid"); f.focus(); } else toast(err.message, "err");
          btn.disabled = false;
        }
      });
      $("#tfaCode", box).focus();
    },

    showRecovery(box, codes, onDone) {
      const text = `Kode cadangan 2FA — ${SITE.name}\nAkun: ${Auth.user.email}\nSetiap kode hanya bisa dipakai sekali.\n\n${codes.join("\n")}\n`;
      box.innerHTML = `
        <div class="success-box" style="padding:0"><div class="check">${I.check}</div><h3>2FA aktif</h3>
          <p>Simpan <b>kode cadangan</b> berikut di tempat aman. Kode ini dipakai bila HP Anda hilang. <b>Tidak akan ditampilkan lagi.</b></p></div>
        <div class="recovery-grid">${codes.map((c) => `<code>${esc(c)}</code>`).join("")}</div>
        <div class="actions-inline">
          <button type="button" class="btn-outline btn-xs" id="rcCopy">Salin semua</button>
          <button type="button" class="btn-outline btn-xs" id="rcDownload">Unduh .txt</button>
        </div>
        <label class="check-row"><input type="checkbox" id="rcSaved"> <span>Saya sudah menyimpan kode cadangan.</span></label>
        <button class="btn-primary block" id="rcDone" disabled>Selesai <span class="arrow">${I.arrow}</span></button>`;
      $("#rcCopy", box).addEventListener("click", () => navigator.clipboard && navigator.clipboard.writeText(text).then(() => toast("Kode cadangan disalin.", "ok")));
      $("#rcDownload", box).addEventListener("click", () => {
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([text], { type: "text/plain" }));
        a.download = "kode-cadangan-gdk.txt"; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      });
      $("#rcSaved", box).addEventListener("change", (e) => { $("#rcDone", box).disabled = !e.target.checked; });
      $("#rcDone", box).addEventListener("click", () => { toast("Verifikasi dua langkah aktif.", "ok"); onDone && onDone(Auth.user); });
    },

    /** Form kode 2FA (saat login, atau saat sesi admin belum terverifikasi). */
    verifyForm(box, onDone, onCancel) {
      let recovery = false;
      const render = () => {
        box.innerHTML = `<form id="tfaVerify" novalidate>
            <div class="field"><label for="tfaV">${recovery ? "Kode cadangan" : "Kode verifikasi"}</label>
              <input id="tfaV" name="code" ${recovery ? 'placeholder="XXXX-XXXX" autocomplete="off"' : 'inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456"'} class="${recovery ? "" : "otp-input"}">
              <span class="err">Kode salah</span></div>
            <button type="submit" class="btn-primary block">Verifikasi <span class="arrow">${I.arrow}</span></button>
            <p class="auth-foot"><a href="#" id="tfaToggle">${recovery ? "Pakai kode dari aplikasi" : "HP tidak tersedia? Pakai kode cadangan"}</a>${onCancel ? ` · <a href="#" id="tfaCancel">Kembali</a>` : ""}</p>
          </form>`;
        const form = $("#tfaVerify", box); const input = $("#tfaV", box);
        input.focus();
        input.addEventListener("input", () => {
          input.closest(".field").classList.remove("invalid");
          if (!recovery && /^\d{6}$/.test(input.value.replace(/\s/g, ""))) form.requestSubmit();
        });
        $("#tfaToggle", box).addEventListener("click", (e) => { e.preventDefault(); recovery = !recovery; render(); });
        if (onCancel) $("#tfaCancel", box).addEventListener("click", (e) => { e.preventDefault(); onCancel(); });
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          const btn = $("button[type=submit]", form); if (btn.disabled) return; btn.disabled = true;
          try { const u = await Auth.verify2fa(input.value.trim()); onDone && onDone(u); }
          catch (err) {
            if (err.code === "mfa_expired") { toast(err.message, "err"); onCancel ? onCancel() : location.reload(); return; }
            $(".err", form).textContent = err.message; input.closest(".field").classList.add("invalid"); input.select(); btn.disabled = false;
          }
        });
      };
      render();
    },

    disableForm(box, onDone) {
      const isAdmin = Auth.user.role === "admin";
      box.innerHTML = `${isAdmin ? `<div class="note-sm">Anda admin: setelah 2FA dinonaktifkan, panel admin akan terkunci sampai 2FA diaktifkan lagi.</div>` : ""}
        <form id="tfaOff" novalidate>
          <div class="field"><label for="offPass">Kata sandi</label><input id="offPass" name="password" type="password" autocomplete="current-password"><span class="err"></span></div>
          <div class="field"><label for="offCode">Kode 2FA atau kode cadangan</label><input id="offCode" name="code" autocomplete="one-time-code"><span class="err"></span></div>
          <button type="submit" class="btn-outline block-btn" style="color:#b42318;border-color:rgba(180,35,24,.35)">Nonaktifkan 2FA</button>
        </form>`;
      const form = $("#tfaOff", box);
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        try { const r = await api("auth/2fa-disable.php", { method: "POST", body: { password: form.password.value, code: form.code.value.trim() } }); Auth.user = r.user; toast("2FA dinonaktifkan.", "ok"); onDone && onDone(); }
        catch (err) {
          const f = err.field && form.elements[err.field];
          if (f) { $(".err", f.closest(".field")).textContent = err.message; f.closest(".field").classList.add("invalid"); } else toast(err.message, "err");
        }
      });
    }
  };

  /* =========================================================
     KONTAK
     ========================================================= */
  function initContactForm() {
    const f = $("#contactForm"); if (!f) return;
    f.addEventListener("submit", (e) => {
      e.preventDefault();
      let ok = true;
      [["name", (v) => v.trim().length >= 2], ["email", (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)], ["message", (v) => v.trim().length >= 10]].forEach(([n, fn]) => {
        const bad = !fn(f.elements[n].value); f.elements[n].closest(".field").classList.toggle("invalid", bad); if (bad) ok = false;
      });
      if (!ok) return;
      const subject = encodeURIComponent("[Website] " + (f.subject.value || "Pertanyaan") + " - " + f.name.value);
      const body = encodeURIComponent(`Nama: ${f.name.value}\nEmail: ${f.email.value}\n\n${f.message.value}`);
      if (e.submitter && e.submitter.dataset.channel === "wa") {
        window.open(`https://wa.me/${SITE.phoneIntl}?text=${encodeURIComponent(`Halo GDK Commerce, saya ${f.name.value} (${f.email.value}).\n\n${f.message.value}`)}`, "_blank", "noopener");
      } else {
        location.href = `mailto:${SITE.email}?subject=${subject}&body=${body}`;
      }
      toast("Terima kasih! Pesan Anda sedang diteruskan.", "ok");
    });
    $$("input, textarea", f).forEach((i) => i.addEventListener("input", () => i.closest(".field").classList.remove("invalid")));
  }

  /* ---------- Fill dynamic contact info ---------- */
  function fillSiteInfo() {
    $$("[data-site]").forEach((el) => {
      const k = el.dataset.site;
      if (k === "email") { el.textContent = SITE.email; el.href = "mailto:" + SITE.email; }
      else if (k === "phone") { el.textContent = SITE.phone; el.href = "tel:+" + SITE.phoneIntl; }
      else if (k === "wa") { el.href = "https://wa.me/" + SITE.phoneIntl; }
      else if (k === "address") el.textContent = SITE.address;
    });
  }

  /* ---------- Boot ---------- */
  async function boot() {
    renderFooter();
    fillSiteInfo();
    initOrb();
    initContactForm();
    initCounters();
    requestAnimationFrame(initReveal);
    // Status login dibutuhkan header & halaman yang dilindungi
    await Auth.refresh();
    renderHeader();
    initHomeProducts();
    initProductPage();
    initAuthPage();
    initAccountPage();
    initPaymentPage();
    // Halaman lain (mis. panel admin) menunggu event ini
    document.dispatchEvent(new CustomEvent("gdk:ready", { detail: { user: Auth.user } }));
  }

  // Helper bersama untuk skrip halaman lain (admin.js)
  window.GDK = { api, auth: Auth, toast, esc, rupiah, fmtDate: (ms) => fmtDate(ms), icons: I, $, $$, logout: doLogout, twofa: TwoFA };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();
})();
