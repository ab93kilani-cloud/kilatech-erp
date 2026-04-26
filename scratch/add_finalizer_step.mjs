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
    
    console.log("Creating 'Le Finaliseur' and updating 'Le Directeur'...");

    // 1. Define the new Finaliseur node
    const finaliseurNode = {
      parameters: {
        model: "gpt-3.5-turbo-instruct",
        prompt: "=Tu es l'Expert en Synthèse Finale pour Kilatech.ma.\\n\\nVOICI LE RAPPORT D'AUDIT ET LE TEXTE :\\n{{ $json.text }}\\n\\nMISSION :\\n1. Applique les corrections suggérées dans le rapport d'audit au texte HTML.\\n2. Supprime TOUTES les notes internes (score, points forts, rapport d'audit, promotion sociale).\\n3. Donne-moi UNIQUEMENT le code HTML final (h1, h2, p, a, strong, ul, li) prêt à être injecté sur le site.\\n\\nRÉPONDS UNIQUEMENT AVEC LE CODE HTML PUR.",
        options: {
          maxTokens: 3000
        }
      },
      name: "Le Finaliseur",
      type: "n8n-nodes-base.openAi",
      typeVersion: 1,
      position: [1200, 300]
    };

    // 2. Add node to workflow if not exists
    if (!wf.nodes.find(n => n.name === "Le Finaliseur")) {
        wf.nodes.push(finaliseurNode);
    }

    // 3. Remap connections: 10 -> 10.5 -> 11
    wf.connections["10. L'Analyste"] = {
      main: [
        [
          {
            node: "Le Finaliseur",
            type: "main",
            index: 0
          }
        ]
      ]
    };
    wf.connections["Le Finaliseur"] = {
      main: [
        [
          {
            node: "11. Le Directeur",
            type: "main",
            index: 0
          }
        ]
      ]
    };

    // 4. Fix Le Directeur (Update his expressions)
    const director = wf.nodes.find(n => n.name.includes("Directeur"));
    if (director) {
        // Fix URL and Payload to use Finaliseur instead of Humaniseur
        // Assuming the previous expression was using $node["7. L'Humaniseur"]
        if (director.parameters.url) {
            director.parameters.url = director.parameters.url.replace(/node\["7\. L'Humaniseur"\]/g, 'node["Le Finaliseur"]');
        }
        // If there's a JSON body for properties
        if (director.parameters.bodyParametersJson) {
            director.parameters.bodyParametersJson = director.parameters.bodyParametersJson.replace(/node\["7\. L'Humaniseur"\]/g, 'node["Le Finaliseur"]');
        }
        // Update general data to point to Finaliseur
        director.parameters.bodyParameters = director.parameters.bodyParameters || {};
        // Find existing parameters and fix them
        if (director.parameters.bodyParameters.parameters) {
            director.parameters.bodyParameters.parameters.forEach(p => {
                if (typeof p.value === 'string') {
                    p.value = p.value.replace(/node\["7\. L'Humaniseur"\]/g, 'node["Le Finaliseur"]');
                    // Also point to $json.text if it was just $json
                    p.value = p.value.replace(/\$json\.text/g, '$node["Le Finaliseur"].json.text');
                }
            });
        }
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Finaliseur created. Director rewired. Chain is solid.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
