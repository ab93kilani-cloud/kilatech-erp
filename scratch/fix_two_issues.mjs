const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  const masterId = "Rbx87xuCY2i1VVBQ";
  const wf = await request(`/workflows/${masterId}`, 'GET');
  
  // FIX 1: Reduce Finaliseur tokens to 1800 (2195 prompt + 1800 = 3995 < 4097)
  const finaliseur = wf.nodes.find(n => n.name === "Le Finaliseur");
  if (finaliseur) {
    finaliseur.parameters.options = finaliseur.parameters.options || {};
    finaliseur.parameters.options.maxTokens = 1800;
    console.log("✅ Fix 1: Finaliseur tokens reduced to 1800 (safe under 4097 limit)");
  }

  // FIX 2: Update GSC Get Products URL to fetch ALL pending products (per_page=150)
  const gscGetProducts = wf.nodes.find(n => n.name === "GSC - Get Products (ERP)");
  if (gscGetProducts) {
    // Add per_page parameter to get all products 
    gscGetProducts.parameters.url = "https://app.kilatech.ma/api/seo-v2/products";
    gscGetProducts.parameters.sendQuery = true;
    gscGetProducts.parameters.queryParametersUi = {
      parameter: [
        { name: "api_token", value: "KILATECH_7382" },
        { name: "per_page", value: "150" },
        { name: "status", value: "pending" }
      ]
    };
    console.log("✅ Fix 2: GSC Get Products now fetches ALL 150 pending products");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("\n🎯 Both fixes applied successfully!");
  console.log("   - Finaliseur: 1800 tokens (no more overflow)");
  console.log("   - ERP: Will now return up to 150 products at once");
}
main().catch(console.error);
