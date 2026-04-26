

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
    console.log("Fetching workflow for Architect repair...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const architect = wf.nodes.find(n => n.name === "Architect");
    if (architect) {
        // Change to Chat mode
        architect.parameters.resource = "chat";
        architect.parameters.operation = "message";
        architect.parameters.model = "gpt-4o";
        
        // Setup proper Chat prompt with System and User messages
        architect.parameters.messages = {
            messageValues: [
                {
                    role: "system",
                    content: "Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la stratégie de contenu parfaite pour un produit en fusionnant les données de l'Auditeur (technique), de l'Espion (concurrence) et la Vision (image)."
                },
                {
                    role: "user",
                    content: "=VOICI LES DONNÉES DU PRODUIT :\nNom: {{ $json.name }}\nDescription ERP: {{ $json.description }}\n\nRAPPORT D'AUDIT TECHNIQUE :\n{{ $json.text }}\n\nDONNÉES DU MARCHÉ (ESPION) :\n{{ JSON.stringify($json.infos_marche) }}\n\nIMAGE DU PRODUIT :\n{{ $json.full_image_url }}\n\nMISSION :\n1. Analyse l'image pour générer un ALT tag SEO descriptif (seo_alt).\n2. Analyse les données du marché pour identifier les mots-clés manquants.\n3. Analyse l'audit technique pour corriger les faiblesses.\n\nRÉPOND UNIQUEMENT EN JSON AVEC CETTE STRUCTURE :\n{\n  \"seo_alt\": \"[Alt tag optimisé]\",\n  \"plan_strategique\": \"[Résumé des optimisations à faire]\",\n  \"keywords_suggeres\": [\"mot1\", \"mot2\", ...]\n}"
                }
            ]
        };
        
        // Remove old 'prompt' parameter if it exists
        delete architect.parameters.prompt;
        
        console.log("Architect node reconfigured for GPT-4o Chat mode.");
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Architect is back online and upgraded!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
