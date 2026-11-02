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
    input.classList.toggle("is-invalid", !!on);
    var field = input.closest(".field");
    if (!field) return;
    var bad = field.querySelector("[data-wpdocs-bad]");
    if (bad) bad.hidden = !on;
  }

  function setInputValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value == null ? "" : String(value);
    setFieldBad(el, false);
  }

  function bindFill() {
    document.addEventListener("click", function (e) {
      var btn = e.target && e.target.closest
        ? e.target.closest("[data-wpdocs-fill]")
        : null;
      if (!btn) return;
      var raw = btn.getAttribute("data-wpdocs-fill") || "";
      var data;
      try {
        data = JSON.parse(raw);
      } catch (err) {
        return;
      }
      if (!data || typeof data !== "object") return;

      if (Object.prototype.hasOwnProperty.call(data, "wp_post_id")) {
        setInputValue("wp_post_id", data.wp_post_id);
        setInputValue("wp_post_type", data.wp_post_type || "post");
        setInputValue("docs_page_id", data.docs_page_id != null ? data.docs_page_id : 0);
        setInputValue("docs_book_slug", data.docs_book_slug || "");
        setInputValue("docs_page_slug", data.docs_page_slug || "");
        setInputValue("external_id", data.external_id || "");
        setInputValue("bind_source", data.source || "manual");
        setInputValue("bind_status", data.status || "active");
      }

      if (Object.prototype.hasOwnProperty.call(data, "wp_path")) {
        setInputValue("wp_path", data.wp_path || "");
        setInputValue(
          "docs_page_id",
          data.docs_page_id != null
            ? data.docs_page_id
            : data.page_id != null
              ? data.page_id
              : 0
        );
        setInputValue("redir_status", data.status || "matched");
        setInputValue("wp_title", data.wp_title || "");
      }

      var form =
        document.getElementById("wpdocs-bind-form") ||
        document.getElementById("wpdocs-redir-form") ||
        document.querySelector(".wenpai-app form[method='post']");
      if (form && form.scrollIntoView) {
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  }

  function bindResolve() {
    var btn = document.querySelector("[data-wpdocs-resolve]");
    var input = document.getElementById("resolve-post");
    var out = document.querySelector("[data-resolve-out]");
    if (!btn || !input) return;

    function run() {
      var v = String(input.value || "").trim();
      if (!v || Number(v) < 1) {
        showPreview(out, "请填写有效的文章 ID 或文档页 ID。", "bad");
        setFieldBad(input, true);
        input.focus();
        return;
      }
      setFieldBad(input, false);
      setBusy(btn, true);
      clearPreview(out);
      showPreview(out, "反查中…", "");
      var q = "post_id=" + encodeURIComponent(v);
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
          if (!res.ok || !res.data) {
            showPreview(out, "未找到绑定。", "bad");
            return;
          }
          var d = res.data;
          var line =
            "文章 #" +
            (d.wp_post_id || "?") +
            " · " +
            (d.docs_book_slug || "") +
            "/" +
            (d.docs_page_slug || "") +
            " · 文档页 #" +
            (d.docs_page_id || 0);
          showPreview(out, line, "ok");
          if (d.wp_post_id) setInputValue("wp_post_id", d.wp_post_id);
          if (d.wp_post_type) setInputValue("wp_post_type", d.wp_post_type);
          if (d.docs_page_id != null) setInputValue("docs_page_id", d.docs_page_id);
          if (d.docs_book_slug) setInputValue("docs_book_slug", d.docs_book_slug);
          if (d.docs_page_slug) setInputValue("docs_page_slug", d.docs_page_slug);
          if (d.external_id != null) setInputValue("external_id", d.external_id);
          if (d.source) setInputValue("bind_source", d.source);
          if (d.status) setInputValue("bind_status", d.status);
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
    input.addEventListener("input", function () {
      setFieldBad(input, false);
      clearPreview(out);
    });
  }

  function bindDryRun() {
    var btn = document.querySelector("[data-wpdocs-dry-run]");
    var input = document.getElementById("dry-run-path");
    var out = document.querySelector("[data-dry-run-out]");
    if (!btn || !input) return;

    function run() {
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
          // Decision::preview uses HTTP codes: 301 / 410 / reserved / none / skip.
          var action = String((res.data && res.data.action) || "none");
          if (action === "301" || action === "matched") {
            showPreview(
              out,
              "已匹配 · 301 → 文档页 #" +
                (res.data.page_id || 0) +
                "（不写命中）",
              "ok"
            );
          } else if (action === "410" || action === "gone") {
            showPreview(out, "已下线 · 410（不写命中）", "ok");
          } else if (action === "reserved") {
            showPreview(out, "保留路径 · 不跳转", "bad");
          } else if (action === "skip") {
            showPreview(out, "待处理 / 未启用 · 不跳转", "bad");
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
        if (form.classList.contains("wpdocs-inline-form")) return;

        form.addEventListener("submit", function (e) {
          var postId = form.querySelector("#wp_post_id");
          var path = form.querySelector("#wp_path");
          var pageId = form.querySelector("#docs_page_id");
          var status = form.querySelector("#redir_status");
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

          if (path && pageId && status) {
            var st = String(status.value || "");
            var pid = Number(String(pageId.value || "0").trim() || "0");
            if (st === "matched" && pid < 1) {
              setFieldBad(pageId, true);
              bad = true;
              if (!path || path.classList.contains("is-invalid") === false) {
                pageId.focus();
              }
            } else {
              setFieldBad(pageId, false);
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

        var watched = form.querySelectorAll("#wp_post_id, #wp_path, #docs_page_id, #redir_status");
        for (var w = 0; w < watched.length; w++) {
          watched[w].addEventListener("input", function () {
            setFieldBad(this, false);
          });
          watched[w].addEventListener("change", function () {
            setFieldBad(this, false);
          });
        }
      })(forms[i]);
    }
  }

  function bindSuggest() {
    var btn = document.querySelector("[data-wpdocs-suggest]");
    var input = document.getElementById("wpdocs-suggest-q");
    var out = document.querySelector("[data-suggest-out]");
    if (!btn || !input || !out) return;

    function renderItems(items) {
      out.innerHTML = "";
      if (!items || !items.length) {
        showPreview(out, "没有匹配的文档页。", "bad");
        return;
      }
      out.hidden = false;
      out.classList.remove("is-ok", "is-bad");
      out.textContent = "";
      var list = document.createElement("ul");
      list.className = "wpdocs-suggest-list";
      for (var i = 0; i < items.length; i++) {
        (function (item) {
          var li = document.createElement("li");
          var pick = document.createElement("button");
          pick.type = "button";
          pick.className = "btn btn-ghost";
          var book = item.docs_book_slug || "";
          var slug = item.docs_page_slug || "";
          var title = item.title || slug || "#" + (item.docs_page_id || 0);
          pick.textContent =
            title +
            " · " +
            (book ? book + "/" : "") +
            slug +
            " · #" +
            (item.docs_page_id || 0);
          pick.addEventListener("click", function () {
            if (item.docs_page_id != null) {
              setInputValue("docs_page_id", item.docs_page_id);
            }
            if (item.docs_book_slug) {
              setInputValue("docs_book_slug", item.docs_book_slug);
            }
            if (item.docs_page_slug) {
              setInputValue("docs_page_slug", item.docs_page_slug);
            }
            showPreview(
              out,
              "已填入 #" +
                (item.docs_page_id || 0) +
                " · " +
                (item.docs_book_slug || "") +
                "/" +
                (item.docs_page_slug || ""),
              "ok"
            );
          });
          li.appendChild(pick);
          list.appendChild(li);
        })(items[i]);
      }
      out.appendChild(list);
    }

    function run() {
      var v = String(input.value || "").trim();
      if (!v) {
        showPreview(out, "请填写搜索关键词。", "bad");
        setFieldBad(input, true);
        input.focus();
        return;
      }
      setFieldBad(input, false);
      setBusy(btn, true);
      clearPreview(out);
      showPreview(out, "搜索中…", "");
      fetch(
        root +
          "/suggest?q=" +
          encodeURIComponent(v) +
          "&count=12&type=page",
        {
          credentials: "same-origin",
          headers: headers(),
        }
      )
        .then(function (r) {
          return r.json().then(function (data) {
            return { ok: r.ok, status: r.status, data: data };
          });
        })
        .then(function (res) {
          if (!res.ok) {
            var code = (res.data && (res.data.code || res.data.error)) || "";
            if (res.status === 503 || code === "wpdocs_no_token") {
              showPreview(
                out,
                "未配置 Token（WPDOCS_TOKEN_ID / SECRET），无法搜索。",
                "bad"
              );
              return;
            }
            showPreview(out, "搜索失败，请稍后重试。", "bad");
            return;
          }
          renderItems((res.data && res.data.items) || []);
        })
        .catch(function () {
          showPreview(out, "暂时无法搜索，请稍后重试。", "bad");
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
    });
  }

  bindFill();
  bindResolve();
  bindSuggest();
  bindDryRun();
  bindFormValidate();
})();
