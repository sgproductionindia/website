(function() {
  "use strict";

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
