/* Think Mybiz portal script */
(function () {
  var csrf = (document.querySelector("input[name=csrf]") || {}).value || "";
  document.addEventListener("click", function (e) {
    var t = e.target.closest("[data-toggle]"); if (t) { document.getElementById(t.getAttribute("data-toggle")).classList.toggle("open"); return; }
    var c = e.target.closest("[data-confirm]"); if (c && !confirm(c.getAttribute("data-confirm"))) { e.preventDefault(); return; }
    var cp = e.target.closest("[data-copy]");
    if (cp) { navigator.clipboard.writeText(cp.getAttribute("data-copy")).then(function () { var o = cp.textContent; cp.textContent = "Copied ✓"; setTimeout(function () { cp.textContent = o; }, 1500); }); return; }
    var q = e.target.closest("[data-qr]");
    if (q) { var box = document.getElementById("qrbox"); document.getElementById("qr").innerHTML = qrSvg(q.getAttribute("data-qr"), 6); box.hidden = false; return; }
    if (e.target.closest("[data-close]")) { document.getElementById("qrbox").hidden = true; return; }
    var tp = e.target.closest(".chips [data-t]");
    if (tp) { var all = JSON.parse(tp.parentNode.getAttribute("data-tpl")), x = all[tp.getAttribute("data-t")], f = document.getElementById("mailform");
      f.subject.value = x[0]; f.body.value = x[1]; f.body.focus(); return; }
    var ai = e.target.closest("[data-ai]");
    if (ai) { var mf = document.getElementById("mailform"); call({ kind: "reply", id: ai.getAttribute("data-id"), intent: ai.getAttribute("data-ai") }, ai,
      function (txt) { mf.body.value = txt; if (!mf.subject.value) mf.subject.value = "Your enquiry"; }); return; }
    if (e.target.closest("[data-aipost]")) { var pf = document.getElementById("postform");
      if (!pf.topic.value.trim()) { alert("Type a topic first."); return; }
      call({ kind: "social", channel: pf.channel.value, topic: pf.topic.value, link: pf.link.value, tone: pf.tone.value }, e.target, function (txt) { pf.body.value = txt; }); return; }
    if (e.target.closest("[data-aistrat]")) { var sf = document.getElementById("stratform");
      call({ kind: "strategy", goal: sf.goal.value }, e.target, function (txt) { sf.out.value = txt; }); return; }
  });
  function call(data, btn, done) {
    var st = btn.parentNode.querySelector(".ai-status"); if (st) st.textContent = "Drafting…"; btn.disabled = true;
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]); fd.append("csrf", csrf);
    fetch("/portal/?p=ai", { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); })
      .then(function (j) { if (j.ok) { done(j.text); if (st) st.textContent = "Draft ready: review and edit before using."; } else if (st) st.textContent = j.error; })
      .catch(function () { if (st) st.textContent = "Could not reach the AI service."; })
      .finally(function () { btn.disabled = false; });
  }
  function qrSvg(text, cell) { if (typeof qrcode !== "function") return "QR library not loaded"; var q = qrcode(0, "M"); q.addData(text); q.make(); return q.createSvgTag({ cellSize: cell, margin: 2 }); }
  document.querySelectorAll("[data-qrfill]").forEach(function (el) { el.innerHTML = qrSvg(el.getAttribute("data-qrfill"), 4); });
})();
