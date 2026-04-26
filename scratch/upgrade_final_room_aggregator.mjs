

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
    console.log("Upgrading Salle de Rédaction Finale to Full Aggregator...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoomName = "Salle de Rédaction Finale";
    const finaliseurName = "Le Finaliseur";

    // 1. Convert to Code Node for full aggregation
    wf.nodes = wf.nodes.filter(n => n.name !== finalRoomName);
    
    const aggregatorNode = {
      parameters: {
        jsCode: "const allInputs = $input.all();\nconst combined = {};\n\n// We merge all fields from all agents into one object\nallInputs.forEach(item => {\n    Object.assign(combined, item.json);\n});\n\nreturn combined;"
      },
      name: finalRoomName,
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1800, 256]
    };
    wf.nodes.push(aggregatorNode);

    // 2. Ensure ALL agents point to this new aggregator
    const teamAgents = ["Redacteur", "🎨 5. Le Designer", "🔗 6. Le Tisseur", "⚖️ 8. Le Rédacteur en Chef", "📊 10. L'Analyste", "📢 9. Le CM"];
    
    teamAgents.forEach(agent => {
        if (!wf.connections[agent]) wf.connections[agent] = { main: [[]] };
        // Check if connection to Final Room exists, if not add it
        const exists = wf.connections[agent].main[0].some(c => c.node === finalRoomName);
        if (!exists) {
            wf.connections[agent].main[0].push({
                node: finalRoomName,
                type: "main",
                index: 0
            });
        }
    });

    // 3. Ensure Aggregator points to Finaliseur
    wf.connections[finalRoomName] = {
        main: [
            [
                {
                    node: finaliseurName,
                    type: "main",
                    index: 0
                }
            ]
        ]
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("Final Newsroom is now a powerful data aggregator!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
