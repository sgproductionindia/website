(function() {
  "use strict";

  function isExternalHttpLink(link) {
    var href = link.getAttribute("href");
    if (!href || href.startsWith("#")) return false;
    if (href.startsWith("mailto:") || href.startsWith("tel:") || href.startsWith("javascript:")) return false;
    if (link.hasAttribute("download")) return false;

    try {
      var url = new URL(href, window.location.href);
      return (url.protocol === "http:" || url.protocol === "https:") && url.origin !== window.location.origin;
    } catch (error) {
      return false;
    }
  }

  function prepareExternalLink(link) {
    if (!isExternalHttpLink(link)) return;
    link.target = "_blank";

    var rel = (link.getAttribute("rel") || "").split(/\s+/).filter(Boolean);
    ["noopener", "noreferrer"].forEach(function(value) {
      if (rel.indexOf(value) === -1) rel.push(value);
    });
    link.setAttribute("rel", rel.join(" "));
  }

  function prepareExternalLinks(root) {
    (root || document).querySelectorAll("a[href]").forEach(prepareExternalLink);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
      prepareExternalLinks(document);
    });
  } else {
    prepareExternalLinks(document);
  }

  if ("MutationObserver" in window) {
    new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches("a[href]")) prepareExternalLink(node);
          if (node.querySelectorAll) prepareExternalLinks(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  document.addEventListener("click", function(event) {
    var link = event.target.closest("a[href]");
    if (link) prepareExternalLink(link);
  }, true);

  document.addEventListener("click", function(event) {
    if (event.defaultPrevented) return;

    var link = event.target.closest("a[href]");
    if (!link) return;

    var href = link.getAttribute("href");
    if (!href) return;

    if (
      href.startsWith("#") ||
      href.startsWith("mailto:") ||
      href.startsWith("javascript:") ||
      href.startsWith("tel:") ||
      link.target === "_blank" ||
      link.hasAttribute("download") ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey
    ) {
      return;
    }

    var url = new URL(href, window.location.href);
    if (url.origin !== window.location.origin) return;
    if (url.pathname === window.location.pathname && url.hash) return;

    var cleanInternalPath = (
      url.pathname === "/" ||
      url.pathname === "/tracks" ||
      url.pathname === "/licensing" ||
      url.pathname === "/artists" ||
      url.pathname.startsWith("/song/") ||
      url.pathname.startsWith("/artist/")
    );

    if (!cleanInternalPath && !url.pathname.endsWith(".html") && !url.pathname.endsWith("/")) return;

    event.preventDefault();
    document.body.classList.add("navigating");
    setTimeout(function() {
      window.location.href = href;
    }, 180);
  });

  window.addEventListener("pageshow", function(event) {
    if (event.persisted) {
      document.body.classList.remove("navigating");
      document.body.style.opacity = "1";
    }
  });
})();
