

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

    // 1. Remove Vision node
    wf.nodes = wf.nodes.filter(n => n.name !== "L'Oeil du Visionnaire");
    
    // 2. Clean up connections
    if (wf.connections["Aggregator"]) {
        wf.connections["Aggregator"].main[0] = wf.connections["Aggregator"].main[0].filter(c => c.node !== "L'Oeil du Visionnaire");
    }
    delete wf.connections["L'Oeil du Visionnaire"];

    // 3. Upgrade Architect to Vision (GPT-4o)
    const architect = wf.nodes.find(n => n.name === "Architect");
    if (architect) {
        // Upgrade to GPT-4o
        architect.parameters.model = "gpt-4o";
        
        // Add Vision configuration to messages
        architect.parameters.messages = {
            messageValues: [
                {
                    role: "user",
                    content: [
                        {
                            type: "text",
                            text: "=Tu es l'Expert Senior en SEO spécialisé dans le MATÉRIEL MÉDICAL chez Kilatech.ma.\n\nTa mission est de concevoir l'ARCHITECTURE de contenu pour : {{ $json.name }}.\n\n--- ANALYSE VISUELLE ---\nAnalyse également l'image fournie pour extraire les détails techniques, les couleurs et le design afin d'enrichir ton plan.\n\nCONSIGNES D'EXPERT MÉDICAL :\n1. TON : Professionnel, technique et rassurant.\n2. CONCURRENCE : NE CITE JAMAIS de noms de concurrents.\n\nRÉPONDS UNIQUEMENT EN JSON :\n{\n  \"Title\": \"...\",\n  \"plan_h1\": \"...\",\n  \"plan_h2\": \"...\",\n  \"plan_h3\": \"...\",\n  \"mots_cles_experts\": [],\n  \"meta_description_longue\": \"...\",\n  \"seo_alt\": \"[Génère ici un ALT TAG optimisé basé sur l'image]\"\n}\n\nBASES DE MARCHÉ :\n{{ JSON.stringify($json.infos_marche) }}"
                        },
                        {
                            type: "image_url",
                            image_url: "={{ $json.full_image_url }}"
                        }
                    ]
                }
            ]
        };

        // Remove old prompt parameter as it's replaced by messages
        delete architect.parameters.prompt;
        
        console.log("Architect upgraded to GPT-4o Vision.");
    }

    // 4. Update Le Directeur to save the new seo_alt from Architect
    const directeur = wf.nodes.find(n => n.name.includes("Directeur"));
    if (directeur) {
        let altParam = directeur.parameters.bodyParameters.parameters.find(p => p.name === "seo_alt");
        if (altParam) {
            altParam.value = "={{ $node[\"Architect\"].json.seo_alt }}";
        } else {
            directeur.parameters.bodyParameters.parameters.push({
                name: "seo_alt",
                value: "={{ $node[\"Architect\"].json.seo_alt }}"
            });
        }
        console.log("Directeur updated to save ALT tag from Architect.");
    }

    // 5. Save the workflow
    const payload = {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    };

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', payload);
    console.log("Workflow successfully rewired: Architect is now the Vision Agent!");

  } catch (err) {
    console.error("Error:", err.message);
  }
}

main();
