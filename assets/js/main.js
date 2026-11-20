/* Think Mybiz Accountants — site script (hand-edited; not generated) */
(function () {
  var t = document.querySelector(".menu-toggle"), l = document.querySelector(".nav-links");
  if (t && l) t.addEventListener("click", function () {
    var o = l.classList.toggle("open"); t.setAttribute("aria-expanded", o ? "true" : "false");
  });
  var y = document.getElementById("year"); if (y) y.textContent = new Date().getFullYear();

  var f = document.getElementById("enquiry");
  if (!f) return;
  // Pre-select a service from ?service=
  var q = new URLSearchParams(location.search).get("service");
  if (q) { var s = f.querySelector("[name=service]"); if (s) for (var i = 0; i < s.options.length; i++) if (s.options[i].value === q) s.selectedIndex = i; }
  f.querySelector("[name=ts]").value = Date.now();

  // Searchable province and town/village fields (native datalist). Province must match the list;
  // any town or village may be typed, with that province's main towns suggested as you type.
  var prov = document.getElementById("f-prov"), town = document.getElementById("f-town"),
      townList = document.getElementById("town-list"), townLabel = document.getElementById("f-town-label"),
      townHelp = document.getElementById("f-town-help");
  if (prov && town) {
    var TOWNS = JSON.parse(prov.getAttribute("data-towns")), OUTSIDE = prov.getAttribute("data-outside"),
        PROVS = Object.keys(TOWNS).concat([OUTSIDE]), current = "";
    function match(v) {
      v = v.trim().toLowerCase();
      for (var i = 0; i < PROVS.length; i++) if (PROVS[i].toLowerCase() === v) return PROVS[i];
      return "";
    }
    function update() {
      var p = match(prov.value);
      prov.setCustomValidity(prov.value && !p ? "Please choose a province from the list." : "");
      if (p) prov.value = p;
      if (p === current) return;
      current = p; town.value = ""; townList.innerHTML = "";
      if (!p) { town.disabled = true; town.placeholder = "Select your province first, then type to search";
        townLabel.textContent = "Town or village"; townHelp.hidden = false; return; }
      town.disabled = false;
      if (p === OUTSIDE) { townLabel.textContent = "Country and town"; town.placeholder = "e.g. Gaborone, Botswana"; townHelp.hidden = true; return; }
      townLabel.textContent = "Town or village"; townHelp.hidden = false;
      town.placeholder = "Type to search towns in " + p;
      TOWNS[p].forEach(function (t) { var o = document.createElement("option"); o.value = t; townList.appendChild(o); });
    }
    prov.addEventListener("input", update);
    prov.addEventListener("change", update);
    prov.addEventListener("blur", update);
  }
  var ok = document.getElementById("enquiry-ok"), err = document.getElementById("enquiry-err");

  function mailtoFallback(d) {
    var body = "Name: " + d.get("name") + "\nPhone: " + d.get("phone") + "\nEmail: " + d.get("email") +
      "\nProvince: " + d.get("province") + "\nTown/village: " + (d.get("town") || "") + "\nService: " + d.get("service") + "\n\n" + d.get("message");
    location.href = "mailto:accounts@thinkmybiz.co.za?subject=" + encodeURIComponent("Website enquiry: " + d.get("service")) +
      "&body=" + encodeURIComponent(body);
  }
  f.addEventListener("submit", function (e) {
    e.preventDefault();
    ok.style.display = "none"; err.style.display = "none";
    if (!f.checkValidity()) { f.reportValidity(); return; }
    var d = new FormData(f), b = f.querySelector("button[type=submit]");
    b.disabled = true; b.textContent = "Sending…";
    fetch("/contact.php", { method: "POST", body: d, headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (r.ok && r.j.ok) { f.reset(); if (prov) { prov.value = ""; prov.dispatchEvent(new Event("change")); } ok.style.display = "block"; }
        else { err.textContent = (r.j && r.j.error) || "Your message could not be sent."; err.style.display = "block"; }
      })
      .catch(function () { mailtoFallback(d); })
      .finally(function () { b.disabled = false; b.textContent = "Send enquiry"; });
  });
})();
