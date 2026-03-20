function el(id) {
  return document.getElementById(id);
}

function setError(msg) {
  el("error").textContent = msg || "";
}

async function login(username, password) {
  const res = await fetch("/api/login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ username, password }),
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    throw new Error(txt || "Login failed");
  }

  const body = await res.json().catch(() => ({}));
  if (!body.ok) {
    throw new Error("Login failed");
  }
}

el("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  setError("");

  const username = el("username").value;
  const password = el("password").value;

  try {
    await login(username, password);
    window.location.href = "/";
  } catch (err) {
    console.error(err);
    setError("Invalid username or password");
  }
});
