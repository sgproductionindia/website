if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("/service-worker.js?v=20260607-pwa-root", {
      scope: "/"
    }).catch(() => {
      // Service workers require HTTPS or localhost.
    });
  });
}
