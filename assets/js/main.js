/* Think Mybiz Accountants — site script (hand-edited; not generated) */
(function () {
  var t = document.querySelector(".menu-toggle"), l = document.querySelector(".nav-links");
  if (t && l) t.addEventListener("click", function () {
    var o = l.classList.toggle("open"); t.setAttribute("aria-expanded", o ? "true" : "false");
  });
  var y = document.getElementById("year"); if (y) y.textContent = new Date().getFullYear();

  // Lead tracking: sends events to Google Analytics only when it is enabled and the visitor accepted cookies.
  function track(name, params) { if (typeof window.gtag === "function") window.gtag("event", name, params || {}); }
  document.addEventListener("click", function (e) {
    var a = e.target.closest("a, button"); if (!a) return;
    var h = a.getAttribute("href") || "";
    if (h.indexOf("tel:") === 0) track("click_call", { link_url: h, page_path: location.pathname });
    else if (h.indexOf("wa.me/") > -1 && !a.hasAttribute("data-track")) track("click_whatsapp", { page_path: location.pathname });
    else if (h.indexOf("mailto:") === 0) track("click_email", { page_path: location.pathname });
    else if (a.hasAttribute("data-track")) track(a.getAttribute("data-track"), { page_path: location.pathname });
    if (a.hasAttribute("data-copy")) {
      var u = a.getAttribute("data-copy");
      if (navigator.clipboard) navigator.clipboard.writeText(u).then(function () { a.textContent = "Link copied"; });
    }
  });
  // Cookie consent (shown only when analytics is configured).
  var cb = document.querySelector(".consent-bar");
  if (cb) {
    var seen = null; try { seen = localStorage.getItem("tmb_consent"); } catch (e) {}
    if (!seen) cb.hidden = false;
    cb.addEventListener("click", function (e) {
      var b = e.target.closest("[data-consent]"); if (!b) return;
      var v = b.getAttribute("data-consent"); try { localStorage.setItem("tmb_consent", v); } catch (e2) {}
      if (v === "yes" && typeof window.gtag === "function") window.gtag("consent", "update", { analytics_storage: "granted" });
      cb.hidden = true;
    });
  }

  var f = document.getElementById("enquiry");
  if (!f) return;
  // Pre-select a service from ?service=
  var q = new URLSearchParams(location.search).get("service");
  if (q) { var s = f.querySelector("[name=service]"); if (s) for (var i = 0; i < s.options.length; i++) if (s.options[i].value === q) s.selectedIndex = i; }
  f.querySelector("[name=ts]").value = Date.now();
  var ok = document.getElementById("enquiry-ok"), err = document.getElementById("enquiry-err");

  // Province -> town/village drop-downs, with free text for towns not listed and for clients outside South Africa.
  var prov = document.getElementById("f-prov"), town = document.getElementById("f-town"),
      townWrap = document.getElementById("town-wrap"), otherWrap = document.getElementById("other-wrap"),
      other = document.getElementById("f-other"), otherLabel = document.getElementById("f-other-label");
  if (prov && town) {
    var TOWNS = JSON.parse(prov.getAttribute("data-towns")), OTHER = prov.getAttribute("data-other"),
        OUTSIDE = prov.getAttribute("data-outside");
    function showOther(on, label, ph) {
      otherWrap.hidden = !on; other.required = on;
      if (label) otherLabel.textContent = label; if (ph) other.placeholder = ph;
      if (!on) other.value = "";
    }
    prov.addEventListener("change", function () {
      var p = prov.value; town.innerHTML = ""; showOther(false);
      if (p === OUTSIDE) {
        townWrap.hidden = true; town.disabled = true;
        showOther(true, "Country and town *", "e.g. Gaborone, Botswana"); return;
      }
      townWrap.hidden = false;
      if (!TOWNS[p]) { town.disabled = true; town.add(new Option("Select a province first", "")); return; }
      town.disabled = false;
      town.add(new Option("Select your town or village", ""));
      TOWNS[p].forEach(function (t) { town.add(new Option(t, t)); });
      town.add(new Option(OTHER + "…", OTHER));
    });
    town.addEventListener("change", function () {
      if (town.value === OTHER) showOther(true, "Your town or village *", "Type your town or village");
      else showOther(false);
    });
  }

  // Searchable drop-down: keeps the native <select> (value, validation, form submit) and adds a
  // button + panel with a search box over the full option list. Clicking always shows the whole list.
  function searchable(sel, onFree) {
    var wrap = document.createElement("div"); wrap.className = "ss";
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel); sel.classList.add("ss-native"); sel.tabIndex = -1;
    var btn = document.createElement("button"); btn.type = "button"; btn.className = "ss-btn";
    btn.setAttribute("aria-haspopup", "listbox"); btn.setAttribute("aria-expanded", "false");
    btn.innerHTML = '<span class="ss-val"></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var panel = document.createElement("div"); panel.className = "ss-panel"; panel.hidden = true;
    panel.innerHTML = '<input type="search" class="ss-search" placeholder="Search…" autocomplete="off" aria-label="Search"><ul class="ss-list" role="listbox"></ul>';
    wrap.appendChild(btn); wrap.appendChild(panel);
    var search = panel.querySelector(".ss-search"), list = panel.querySelector(".ss-list"), hi = -1;
    var label = document.querySelector('label[for="' + sel.id + '"]');
    if (label) label.addEventListener("click", function (e) { e.preventDefault(); open(); });

    function items() { return Array.prototype.slice.call(list.querySelectorAll("li[data-value]")); }
    function mark(i) { var it = items(); it.forEach(function (li, k) { li.classList.toggle("hi", k === i); }); hi = i;
      if (it[i]) it[i].scrollIntoView({ block: "nearest" }); }
    function render(term) {
      list.innerHTML = ""; term = term.trim().toLowerCase(); var n = 0;
      Array.prototype.forEach.call(sel.options, function (o) {
        if (!o.value || (term && o.text.toLowerCase().indexOf(term) < 0)) return;
        var li = document.createElement("li"); li.setAttribute("role", "option"); li.dataset.value = o.value; li.textContent = o.text;
        if (o.value === sel.value) li.classList.add("sel");
        list.appendChild(li); n++;
      });
      if (term && onFree) {
        var li = document.createElement("li"); li.dataset.value = "\u0000free"; li.className = "ss-free";
        li.textContent = "Not listed? Use \u201c" + search.value.trim() + "\u201d"; list.appendChild(li); n++;
      } else if (!n) { var e = document.createElement("li"); e.className = "ss-empty"; e.textContent = "No match"; list.appendChild(e); }
      mark(term ? 0 : Math.max(0, items().findIndex(function (li) { return li.classList.contains("sel"); })));
    }
    function open() { if (sel.disabled || !panel.hidden) return; panel.hidden = false; btn.setAttribute("aria-expanded", "true");
      wrap.classList.add("open"); search.value = ""; render(""); search.focus(); }
    function close() { panel.hidden = true; btn.setAttribute("aria-expanded", "false"); wrap.classList.remove("open"); }
    function choose(v) {
      if (v === "\u0000free") { var txt = search.value.trim(); close(); onFree(txt); sync(); btn.focus(); return; }
      sel.value = v; sel.dispatchEvent(new Event("change", { bubbles: true })); close(); btn.focus();
    }
    function sync() {
      var o = sel.options[sel.selectedIndex]; btn.disabled = sel.disabled;
      btn.querySelector(".ss-val").textContent = o ? o.text : "";
      btn.classList.toggle("ph", !sel.value); wrap.classList.toggle("invalid", false);
    }
    btn.addEventListener("click", function () { panel.hidden ? open() : close(); });
    btn.addEventListener("keydown", function (e) { if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); } });
    search.addEventListener("input", function () { render(search.value); });
    search.addEventListener("keydown", function (e) {
      var it = items();
      if (e.key === "ArrowDown") { e.preventDefault(); mark(Math.min(hi + 1, it.length - 1)); }
      else if (e.key === "ArrowUp") { e.preventDefault(); mark(Math.max(hi - 1, 0)); }
      else if (e.key === "Enter") { e.preventDefault(); if (it[hi]) choose(it[hi].dataset.value); }
      else if (e.key === "Escape") { e.preventDefault(); close(); btn.focus(); }
      else if (e.key === "Tab") close();
    });
    list.addEventListener("mousedown", function (e) { var li = e.target.closest("li[data-value]"); if (li) { e.preventDefault(); choose(li.dataset.value); } });
    document.addEventListener("mousedown", function (e) { if (!wrap.contains(e.target)) close(); });
    sel.addEventListener("change", sync);
    sel.addEventListener("invalid", function () { wrap.classList.add("invalid"); });
    sel.addEventListener("focus", function () { btn.focus(); });
    sync();
    return { sync: sync };
  }
  if (prov && town) {
    var provSS = searchable(prov);
    var townSS = searchable(town, function (txt) {   // typed town/village not in the list
      if (!txt) return; town.value = OTHER; town.dispatchEvent(new Event("change", { bubbles: true })); other.value = txt;
    });
    prov.addEventListener("change", function () { townSS.sync(); if (prov.value) err.style.display = "none"; });
  }

  function mailtoFallback(d) {
    var body = "Name: " + d.get("name") + "\nPhone: " + d.get("phone") + "\nEmail: " + d.get("email") +
      "\nProvince: " + d.get("province") + "\nTown/village: " + (d.get("town_other") || d.get("town") || "") + "\nService: " + d.get("service") + "\n\n" + d.get("message");
    location.href = "mailto:accounts@thinkmybiz.co.za?subject=" + encodeURIComponent("Website enquiry: " + d.get("service")) +
      "&body=" + encodeURIComponent(body);
  }
  f.addEventListener("submit", function (e) {
    e.preventDefault();
    ok.style.display = "none"; err.style.display = "none";
    if (!f.checkValidity()) { var bad = f.querySelector(":invalid"); if (bad && bad.classList.contains("ss-native")) { bad.dispatchEvent(new Event("invalid")); bad.closest(".ss").querySelector(".ss-btn").focus(); err.textContent = "Please select your province."; err.style.display = "block"; } else f.reportValidity(); return; }
    var d = new FormData(f), b = f.querySelector("button[type=submit]");
    b.disabled = true; b.textContent = "Sending…";
    fetch("/contact.php", { method: "POST", body: d, headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (r.ok && r.j.ok) { track("generate_lead", { service: d.get("service"), province: d.get("province") }); f.reset(); if (prov) prov.dispatchEvent(new Event("change")); ok.style.display = "block"; }
        else { err.textContent = (r.j && r.j.error) || "Your message could not be sent."; err.style.display = "block"; }
      })
      .catch(function () { mailtoFallback(d); })
      .finally(function () { b.disabled = false; b.textContent = "Send enquiry"; });
  });
})();
