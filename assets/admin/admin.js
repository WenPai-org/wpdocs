(function () {
  "use strict";

  var cfg = window.wpdocsAdmin || {};
  var root = (cfg.root || "/wp-json/wpdocs/v1").replace(/\/$/, "");
  var nonce = cfg.nonce || "";

  function headers() {
    return {
      Accept: "application/json",
      "X-WP-Nonce": nonce,
    };
  }

  function setBusy(el, on) {
    if (!el) return;
    el.classList.toggle("is-busy", !!on);
    if ("disabled" in el) {
      el.disabled = !!on;
    }
  }

  function showPreview(out, msg, kind) {
    if (!out) return;
    out.hidden = false;
    out.textContent = msg;
    out.classList.remove("is-ok", "is-bad");
    if (kind === "ok") out.classList.add("is-ok");
    if (kind === "bad") out.classList.add("is-bad");
  }

  function clearPreview(out) {
    if (!out) return;
    out.hidden = true;
    out.textContent = "";
    out.classList.remove("is-ok", "is-bad");
  }

  function setFieldBad(input, on) {
    if (!input) return;
    var field = input.closest(".field");
    if (!field) return;
    var bad = field.querySelector("[data-wpdocs-bad]");
    if (bad) bad.hidden = !on;
    input.classList.toggle("is-invalid", !!on);
  }

  function bindResolve() {
    var btn = document.querySelector("[data-wpdocs-resolve]");
    var input = document.getElementById("resolve-post");
    var out = document.querySelector("[data-resolve-out]");
    if (!btn || !input) return;

    function run() {
      if (btn.disabled) return;
      var v = String(input.value || "").trim();
      if (!v) {
        showPreview(out, "暂时无法反查，请填写文章 ID 或文档页 ID。", "bad");
        input.focus();
        return;
      }
      setBusy(btn, true);
      clearPreview(out);
      showPreview(out, "反查中…", "");
      var q =
        "wp_post_id=" +
        encodeURIComponent(v) +
        "&docs_page_id=" +
        encodeURIComponent(v);
      fetch(root + "/bindings/resolve?" + q, {
        credentials: "same-origin",
        headers: headers(),
      })
        .then(function (r) {
          return r.json().then(function (data) {
            return { ok: r.ok, data: data };
          });
        })
        .then(function (res) {
          var row = res.data && res.data.row;
          if (!row) {
            showPreview(out, "没有对应绑定。", "bad");
            return;
          }
          showPreview(
            out,
            "文章 " +
              row.wp_post_id +
              " ↔ " +
              (row.docs_book_slug || "") +
              "/" +
              (row.docs_page_slug || "") +
              "（#" +
              row.docs_page_id +
              "）",
            "ok"
          );
        })
        .catch(function () {
          showPreview(out, "暂时无法反查，请稍后重试。", "bad");
        })
        .finally(function () {
          setBusy(btn, false);
        });
    }

    btn.addEventListener("click", run);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        run();
      }
    });
  }

  function bindDryRun() {
    var btn = document.querySelector("[data-wpdocs-dry-run]");
    var input = document.getElementById("dry-path");
    var out = document.querySelector("[data-dry-out]");
    if (!btn || !input) return;

    function run() {
      if (btn.disabled) return;
      var v = String(input.value || "").trim();
      if (!v || v.charAt(0) !== "/") {
        showPreview(out, "暂时无法试跳转，请填写以 / 开头的路径。", "bad");
        setFieldBad(input, true);
        input.focus();
        return;
      }
      setFieldBad(input, false);
      setBusy(btn, true);
      clearPreview(out);
      showPreview(out, "试跳转中…", "");
      fetch(root + "/redirects/dry-run?path=" + encodeURIComponent(v), {
        credentials: "same-origin",
        headers: headers(),
      })
        .then(function (r) {
          return r.json().then(function (data) {
            return { ok: r.ok, data: data };
          });
        })
        .then(function (res) {
          var action = (res.data && res.data.action) || "none";
          if (action === "matched") {
            showPreview(
              out,
              "已匹配 · 301 → 文档页 #" +
                (res.data.page_id || 0) +
                "（不写命中）",
              "ok"
            );
          } else if (action === "gone") {
            showPreview(out, "已失效 · 410（不写命中）", "ok");
          } else if (action === "reserved") {
            showPreview(out, "保留路径 · 不跳转", "bad");
          } else {
            showPreview(out, "未匹配 · 不写命中", "bad");
          }
        })
        .catch(function () {
          showPreview(out, "暂时无法试跳转，请稍后重试。", "bad");
        })
        .finally(function () {
          setBusy(btn, false);
        });
    }

    btn.addEventListener("click", run);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        run();
      }
    });
    input.addEventListener("input", function () {
      setFieldBad(input, false);
      clearPreview(out);
    });
  }

  function bindFormValidate() {
    var forms = document.querySelectorAll(".wenpai-app form[method='post']");
    for (var i = 0; i < forms.length; i++) {
      (function (form) {
        form.addEventListener("submit", function (e) {
          var postId = form.querySelector("#wp_post_id");
          var path = form.querySelector("#wp_path");
          var bad = false;

          if (postId) {
            var pv = String(postId.value || "").trim();
            if (!pv || Number(pv) < 1) {
              setFieldBad(postId, true);
              bad = true;
              postId.focus();
            } else {
              setFieldBad(postId, false);
            }
          }

          if (path) {
            var xv = String(path.value || "").trim();
            if (!xv || xv.charAt(0) !== "/") {
              setFieldBad(path, true);
              bad = true;
              if (!postId) path.focus();
            } else {
              setFieldBad(path, false);
            }
          }

          if (bad) {
            e.preventDefault();
            return;
          }

          form.classList.add("is-busy");
          var btns = form.querySelectorAll("button, input[type='submit']");
          for (var b = 0; b < btns.length; b++) {
            btns[b].disabled = true;
          }
        });

        var watched = form.querySelectorAll("#wp_post_id, #wp_path");
        for (var w = 0; w < watched.length; w++) {
          watched[w].addEventListener("input", function () {
            setFieldBad(this, false);
          });
        }
      })(forms[i]);
    }
  }

  bindResolve();
  bindDryRun();
  bindFormValidate();
})();
