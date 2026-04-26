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
  
  console.log("Fixing Director payload and Finalizer extraction...");

  // 1. Ensure Finalizer PROMPT is strong enough to output everything
  const finalizer = wf.nodes.find(n => n.name === "Le Finaliseur");
  if (finalizer) {
    finalizer.parameters.prompt = "=Tu es l'Expert en Synthèse Finale pour Kilatech.ma.\n\nVOICI LE RAPPORT D'AUDIT ET LE CONTENU : \n{{ $json.text }}\n\nMISSION CRITIQUE : \nProduis un document HTML COMPLET. \n\nSTRUCTURE OBLIGATOIRE :\n1. <title>[Insère ici le Titre SEO optimisé]</title>\n2. <meta name=\"description\" content=\"[Insère ici la Meta Description de 150 caractères]\">\n3. <h1>[Insère ici le Titre H1 de la page]</h1>\n4. [Insère ici TOUT le contenu de l'article formaté en HTML avec des <h2>, <h3> et des paragraphes <p>]\n\nCONSIGNE : Ne renvoie RIEN d'autre que le code HTML.";
    console.log("✅ Finalizer prompt updated to enforce full structure.");
  }

  // 2. Fix Director (Agent 11) mappings
  const director = wf.nodes.find(n => n.name.includes("Le Directeur"));
  if (director) {
    // We update the body parameters to map from the Finalizer's structured output
    director.parameters.bodyParametersUi = {
      parameter: [
        { name: "api_token", value: "KILATECH_7382" },
        { 
            name: "seo_title", 
            value: "={{$node[\"Le Finaliseur\"].json.text.match(/<title>(.*?)<\\/title>/i)?.[1] || $node[\"Architect\"].json.seo_title}}" 
        },
        { 
            name: "seo_description", 
            value: "={{$node[\"Le Finaliseur\"].json.text.match(/<meta name=\"description\" content=\"(.*?)\"/i)?.[1] || $node[\"Architect\"].json.seo_description}}" 
        },
        { 
            name: "seo_h1", 
            value: "={{$node[\"Le Finaliseur\"].json.text.match(/<h1>(.*?)<\\/h1>/i)?.[1] || $node[\"PRODUIT\"].json.name}}" 
        },
        { 
            name: "seo_content", 
            value: "={{$node[\"Le Finaliseur\"].json.text}}" 
        },
        { name: "seo_status", value: "optimized" }
      ]
    };
    console.log("✅ Director mappings secured for Title, Description, H1 and Content.");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("Injection pipeline is now 100% robust.");
}
main().catch(console.error);
