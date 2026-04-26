

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
    console.log("Installing the Team Dispatcher...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const architectName = "Architect";
    const dispatcherName = "Le Dispatcher d'Ordres";

    // 1. Create the Dispatcher Node (Pass-through Code node)
    const dispatcherNode = {
      parameters: {
        jsCode: "return $input.all();"
      },
      name: dispatcherName,
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [850, 128]
    };
    wf.nodes.push(dispatcherNode);

    // 2. Point Architect -> Dispatcher
    wf.connections[architectName].main[0] = [
        {
            node: dispatcherName,
            type: "main",
            index: 0
        }
    ];

    // 3. Point Dispatcher -> All Team Agents
    const teamAgents = ["Redacteur", "🎨 5. Le Designer", "🔗 6. Le Tisseur", "⚖️ 8. Le Rédacteur en Chef", "📊 10. L'Analyste", "📢 9. Le CM"];
    
    wf.connections[dispatcherName] = {
        main: [
            teamAgents.map(agent => ({
                node: agent,
                type: "main",
                index: 0
            }))
        ]
    };

    // 4. Clean up old connections from Architect to agents
    // (They are already replaced by step 2, but just in case)

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Dispatcher installed. The team is now perfectly synchronized!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
