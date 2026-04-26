

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const MASTER_WF_ID = 'Rbx87xuCY2i1VVBQ';

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
    console.log("Naming the items in the Final Newsroom...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoomName = "Salle de Rédaction Finale";

    const aggregatorNode = wf.nodes.find(n => n.name === finalRoomName);
    if (aggregatorNode) {
        aggregatorNode.parameters.jsCode = `
const allInputs = $input.all();
const namedReport = {
    id_produit: allInputs[0].json.id || "N/A",
    nom_produit: allInputs[0].json.name || "N/A",
    details_agents: {}
};

// We identify each contribution by its content structure
allInputs.forEach(item => {
    const data = item.json;
    if (data.text && data.text.includes("<h1>")) {
        namedReport.details_agents.redacteur = data;
    } else if (data.seo_alt || (data.text && data.text.includes("ALT"))) {
        namedReport.details_agents.designer = data;
    } else if (data.text && data.text.includes("LinkedIn")) {
        namedReport.details_agents.community_manager = data;
    } else if (data.text && data.text.includes("Lien")) {
        namedReport.details_agents.tisseur = data;
    } else if (data.text && data.text.includes("ANALYSE")) {
        namedReport.details_agents.analyste = data;
    } else if (data.text && data.text.includes("CORRECTION")) {
        namedReport.details_agents.redacteur_chef = data;
    } else {
        // Fallback: merge anything else
        Object.assign(namedReport.details_agents, data);
    }
});

return namedReport;
        `;
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("Items are now named and structured!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
