

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
    console.log("Fetching current workflow...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    // 1. Create the Vision Node
    const visionNode = {
      parameters: {
        model: "gpt-4o",
        options: {},
        messages: {
          messageValues: [
            {
              role: "user",
              content: [
                {
                  type: "text",
                  text: "=Tu es l'Expert Vision de Kilatech.ma. Analyse cette image de produit : {{ $json.name }}.\n\nDonne-moi :\n1. Un ALT TAG optimisé SEO.\n2. Une description technique des caractéristiques visibles (matériau, couleur, design).\n\nRéponds en JSON uniquement :\n{\n  \"alt_tag\": \"...\",\n  \"visual_analysis\": \"...\"\n}"
                },
                {
                  type: "image_url",
                  image_url: "={{ $json.full_image_url }}"
                }
              ]
            }
          ]
        }
      },
      id: "vision-expert-node-id",
      name: "L'Oeil du Visionnaire",
      type: "n8n-nodes-base.openAi",
      typeVersion: 1.1, // Adjust based on version
      position: [688, -100], // Above Architect
      credentials: {
        openAiApi: {
          id: "DmSfvKlB2CMFj7eE",
          name: "OpenAi account 2"
        }
      }
    };

    // 2. Add the node to the list if not exists
    if (!wf.nodes.find(n => n.name === visionNode.name)) {
      wf.nodes.push(visionNode);
      console.log("Added Vision node.");
    }

    // 3. Update connections
    // Aggregator -> Vision Expert
    if (!wf.connections["Aggregator"]) wf.connections["Aggregator"] = { main: [[]] };
    if (!wf.connections["Aggregator"].main[0].find(c => c.node === visionNode.name)) {
        wf.connections["Aggregator"].main[0].push({
            node: visionNode.name,
            type: "main",
            index: 0
        });
    }

    // Vision Expert -> Architect
    wf.connections[visionNode.name] = {
        main: [
            [
                {
                    node: "Architect",
                    type: "main",
                    index: 0
                }
            ]
        ]
    };

    // 4. Update Architect prompt to include Vision data
    const architect = wf.nodes.find(n => n.name === "Architect");
    if (architect) {
        if (!architect.parameters.prompt.includes("ANALYSE VISUELLE")) {
            architect.parameters.prompt = architect.parameters.prompt.replace(
                "BASES DE MARCHÉ",
                "ANALYSE VISUELLE DU PRODUIT (L'Oeil du Visionnaire) :\n{{ $node[\"L'Oeil du Visionnaire\"].json.visual_analysis }}\n\nALT TAG RECOMMANDÉ :\n{{ $node[\"L'Oeil du Visionnaire\"].json.alt_tag }}\n\nBASES DE MARCHÉ"
            );
            console.log("Updated Architect prompt.");
        }
    }

    // 5. Update Le Directeur to save the ALT tag
    const directeur = wf.nodes.find(n => n.name.includes("Directeur"));
    if (directeur) {
        const altParam = directeur.parameters.bodyParameters.parameters.find(p => p.name === "seo_alt");
        if (!altParam) {
            directeur.parameters.bodyParameters.parameters.push({
                name: "seo_alt",
                value: "={{ $node[\"L'Oeil du Visionnaire\"].json.alt_tag }}"
            });
            console.log("Updated Directeur to save ALT tag.");
        }
    }

    // 6. Save the workflow
    const payload = {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', payload);

    console.log("Workflow successfully updated with Vision Agent!");

  } catch (err) {
    console.error("Error:", err.message);
  }
}

main();
