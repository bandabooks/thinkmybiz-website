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
  var ok = document.getElementById("enquiry-ok"), err = document.getElementById("enquiry-err");

  function mailtoFallback(d) {
    var body = "Name: " + d.get("name") + "\nPhone: " + d.get("phone") + "\nEmail: " + d.get("email") +
      "\nProvince/town: " + d.get("location") + "\nService: " + d.get("service") + "\n\n" + d.get("message");
    location.href = "mailto:accounts@thinkmybiz.co.za?subject=" + encodeURIComponent("Website enquiry: " + d.get("service")) +
      "&body=" + encodeURIComponent(body);
  }
  f.addEventListener("submit", function (e) {
    e.preventDefault();
    ok.style.display = "none"; err.style.display = "none";
    var d = new FormData(f), b = f.querySelector("button[type=submit]");
    b.disabled = true; b.textContent = "Sending…";
    fetch("/contact.php", { method: "POST", body: d, headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (r.ok && r.j.ok) { f.reset(); ok.style.display = "block"; }
        else { err.textContent = (r.j && r.j.error) || "Your message could not be sent."; err.style.display = "block"; }
      })
      .catch(function () { mailtoFallback(d); })
      .finally(function () { b.disabled = false; b.textContent = "Send enquiry"; });
  });
})();
