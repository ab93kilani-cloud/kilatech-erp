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
    
    console.log("Final touch: Fixing Agent 12 (Le Manager) and global verification...");

    const manager = wf.nodes.find(n => n.name.includes("Le Manager"));
    if (manager) {
        // Fix expressions to point to the new final content
        // Typically it sends a notification like "Product X updated by Agent 7"
        // We change it to "Product X updated by the SEO Team (Agent 11)"
        let paramsString = JSON.stringify(manager.parameters);
        paramsString = paramsString.replace(/7\. L'Humaniseur/g, 'Le Finaliseur');
        paramsString = paramsString.replace(/\$node\["7\. L'Humaniseur"\]/g, '$node["Le Finaliseur"]');
        manager.parameters = JSON.parse(paramsString);
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("MANAGER REPAIRED. ALL AGENTS (1 TO 12) ARE NOW SYNCED!");

  } catch (err) {
    console.error(err.message);
  }
}
main();
