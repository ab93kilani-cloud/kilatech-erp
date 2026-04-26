import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: {
      'X-N8N-API-KEY': API_KEY,
      'Content-Type': 'application/json'
    }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  try {
    const masterId = "Rbx87xuCY2i1VVBQ";
    let wf = await request(`/workflows/${masterId}`, 'GET');
    
    console.log("Fixing WhatsApp Manager and linking Analytics trigger...");

    // 1. Fix Manager Payload
    const manager = wf.nodes.find(n => n.name.includes("Le Manager"));
    if (manager && manager.parameters.queryString) {
        // Find the "text" parameter in JSON or Query Params
        // Based on screenshot it is in a "JSON" field under Specify Query Parameters
        if (manager.parameters.jsonParameters) {
            manager.parameters.queryString = manager.parameters.queryString.replace(/node\["Gestionnaire"\]/g, 'node["PRODUIT"]');
        }
    }
    // Deep replacement for safety
    let wfStr = JSON.stringify(wf);
    wfStr = wfStr.replace(/\$node\["Gestionnaire"\]/g, '$node["PRODUIT"]');
    wf = JSON.parse(wfStr);

    // 2. Link Pilot -> Analytics
    // Check if link already exists, if not add it
    wf.connections["⏰ Pilote Automatique"] = wf.connections["⏰ Pilote Automatique"] || { main: [[]] };
    const pilotOut = wf.connections["⏰ Pilote Automatique"].main[0];
    if (!pilotOut.find(t => t.node === "GSC - Fetch GSC Analytics")) {
        pilotOut.push({ node: "GSC - Fetch GSC Analytics", type: "main", index: 0 });
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("WhatsApp Fixed. Analytics Triggered. Systems Nominal.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
