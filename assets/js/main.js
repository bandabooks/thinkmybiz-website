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
  var ok = document.getElementById("enquiry-ok"), err = document.getElementById("enquiry-err");

  function mailtoFallback(d) {
    var body = "Name: " + d.get("name") + "\nPhone: " + d.get("phone") + "\nEmail: " + d.get("email") +
      "\nProvince: " + d.get("province") + "\nTown/village: " + (d.get("town_other") || d.get("town") || "") + "\nService: " + d.get("service") + "\n\n" + d.get("message");
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
        if (r.ok && r.j.ok) { f.reset(); if (prov) prov.dispatchEvent(new Event("change")); ok.style.display = "block"; }
        else { err.textContent = (r.j && r.j.error) || "Your message could not be sent."; err.style.display = "block"; }
      })
      .catch(function () { mailtoFallback(d); })
      .finally(function () { b.disabled = false; b.textContent = "Send enquiry"; });
  });
})();
