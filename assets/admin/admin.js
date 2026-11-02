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

  function text(el, msg) {
    if (!el) return;
    el.hidden = false;
    el.textContent = msg;
  }

  function bindResolve() {
    var btn = document.querySelector("[data-wpdocs-resolve]");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var input = document.getElementById("resolve-post");
      var out = document.querySelector("[data-resolve-out]");
      var v = input && input.value ? String(input.value).trim() : "";
      if (!v) {
        text(out, "暂时无法反查，请填写文章 ID 或文档页 ID。");
        return;
      }
      var q = "wp_post_id=" + encodeURIComponent(v) + "&docs_page_id=" + encodeURIComponent(v);
      text(out, "反查中…");
      fetch(root + "/bindings/resolve?" + q, { credentials: "same-origin", headers: headers() })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var row = data && data.row;
          if (!row) {
            text(out, "没有对应绑定。");
            return;
          }
          text(
            out,
            "文章 " +
              row.wp_post_id +
              " ↔ " +
              (row.docs_book_slug || "") +
              "/" +
              (row.docs_page_slug || "") +
              "（#" +
              row.docs_page_id +
              "）"
          );
        })
        .catch(function () {
          text(out, "暂时无法反查，请稍后重试。");
        });
    });
  }

  function bindDryRun() {
    var btn = document.querySelector("[data-wpdocs-dry-run]");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var input = document.getElementById("dry-path");
      var out = document.querySelector("[data-dry-out]");
      var v = input && input.value ? String(input.value).trim() : "";
      if (!v || v.charAt(0) !== "/") {
        text(out, "暂时无法试跳转，请填写以 / 开头的路径。");
        return;
      }
      text(out, "试跳转中…");
      fetch(root + "/redirects/dry-run?path=" + encodeURIComponent(v), {
        credentials: "same-origin",
        headers: headers(),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var action = (data && data.action) || "none";
          if (action === "matched") {
            text(out, "已匹配 · 301 → 文档页 #" + (data.page_id || 0) + "（不写命中）");
          } else if (action === "gone") {
            text(out, "已失效 · 410（不写命中）");
          } else if (action === "reserved") {
            text(out, "保留路径 · 不跳转");
          } else {
            text(out, "未匹配 · 不写命中");
          }
        })
        .catch(function () {
          text(out, "暂时无法试跳转，请稍后重试。");
        });
    });
  }

  bindResolve();
  bindDryRun();
})();
