function el(id) {
  return document.getElementById(id);
}

const state = {
  lastConfig: null,
  toastTimer: null,
};

function setStatus(text, kind) {
  const node = el("status");
  node.textContent = text;
  node.dataset.kind = kind || "";
}

function toast(msg, kind) {
  const node = el("toast");
  node.textContent = msg;
  node.dataset.kind = kind || "";
  node.classList.add("show");
  clearTimeout(state.toastTimer);
  state.toastTimer = setTimeout(() => node.classList.remove("show"), 2400);
}

function nowStamp() {
  const d = new Date();
  const p2 = (n) => String(n).padStart(2, "0");
  return `${p2(d.getHours())}:${p2(d.getMinutes())}:${p2(d.getSeconds())}`;
}

function populateForm(cfg) {
  el("refresh_interval").value = cfg.refresh_interval;
  el("theme").value = cfg.theme;
  el("alerts_enabled").checked = !!cfg.alerts_enabled;
  el("max_items").value = cfg.max_items;
  el("banner_message").value = cfg.banner_message;
}

function renderConfig(cfg) {
  state.lastConfig = cfg;
  el("configView").textContent = JSON.stringify(cfg, null, 2);
  el("lastUpdate").textContent = `Updated ${nowStamp()}`;
}

async function loadConfig() {
  setStatus("Fetching config...", "busy");
  const res = await fetch("/api/config", { method: "GET" });
  if (res.status === 401) {
    window.location.href = "/public/login.html";
    return;
  }
  const body = await res.json();
  if (!res.ok || !body.ok) {
    setStatus("Failed to load", "bad");
    toast("Failed to load config", "bad");
    return;
  }

  setStatus("Connected", "good");
  renderConfig(body.config);
  populateForm(body.config);
}

function collectForm() {
  return {
    refresh_interval: parseInt(el("refresh_interval").value, 10),
    theme: el("theme").value,
    alerts_enabled: el("alerts_enabled").checked,
    max_items: parseInt(el("max_items").value, 10),
    banner_message: el("banner_message").value,
  };
}

async function saveConfig(payload) {
  setStatus("Saving...", "busy");
  const res = await fetch("/api/config", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  if (res.status === 401) {
    window.location.href = "/public/login.html";
    return;
  }

  const body = await res.json().catch(() => ({}));
  if (!res.ok || !body.ok) {
    setStatus("Validation error", "bad");
    const msg = body && body.errors ? `Error: ${Object.keys(body.errors).join(", ")}` : "Save failed";
    toast(msg, "bad");
    if (body && body.config) {
      renderConfig(body.config);
    }
    return;
  }

  setStatus("Saved", "good");
  renderConfig(body.config);
  toast("Config saved", "good");
}

function wireUp() {
  el("configForm").addEventListener("submit", (e) => {
    e.preventDefault();
    saveConfig(collectForm());
  });

  el("reloadBtn").addEventListener("click", () => {
    loadConfig().catch((err) => {
      console.error(err);
      setStatus("Disconnected", "bad");
      toast("Could not reach server", "bad");
    });
  });

  el("logoutBtn").addEventListener("click", async () => {
    try {
      await fetch("/api/logout", { method: "POST" });
    } finally {
      window.location.href = "/public/login.html";
    }
  });
}

wireUp();
loadConfig().catch((err) => {
  console.error(err);
  setStatus("Disconnected", "bad");
  toast("Could not reach server", "bad");
});
