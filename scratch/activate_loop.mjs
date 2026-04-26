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

  // Find exact node names
  const produit = wf.nodes.find(n => n.name === "PRODUIT");
  const manager = wf.nodes.find(n => n.name.includes("Le Manager"));

  console.log("PRODUIT node:", produit ? `"${produit.name}"` : "NOT FOUND");
  console.log("Manager node:", manager ? `"${manager.name}"` : "NOT FOUND");

  if (!produit || !manager) {
    console.error("Cannot find required nodes!");
    return;
  }

  // Connect Le Manager → PRODUIT (loop back)
  // This tells PRODUIT to process the next item
  wf.connections[manager.name] = wf.connections[manager.name] || { main: [[]] };
  
  // Make sure manager has an output going back to PRODUIT
  const managerConnections = wf.connections[manager.name];
  if (!managerConnections.main[0]) managerConnections.main[0] = [];

  // Check if loop-back already exists
  const alreadyLinked = managerConnections.main[0].find(c => c.node === "PRODUIT");
  if (!alreadyLinked) {
    managerConnections.main[0].push({
      node: "PRODUIT",
      type: "main",
      index: 0  // index 0 = "loop" input of SplitInBatches
    });
    console.log(`✅ Loop connected: "${manager.name}" → PRODUIT`);
  } else {
    console.log("Loop already exists!");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("\n🔄 LOOP ACTIVATED! Workflow will now process ALL 150 products automatically.");
}
main().catch(console.error);
