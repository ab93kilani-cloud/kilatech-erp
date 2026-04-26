const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_DESIGNER_FIXED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Change Designer from "Completion" mode to "Chat" mode to support o4-mini
let designerNode = nodes.find(n => n.name === '🎨 5. Le Designer');
if (designerNode) {
    designerNode.parameters.resource = 'chat';
    designerNode.parameters.operation = 'message';
    designerNode.parameters.model = 'o4-mini';
    designerNode.parameters.messages = {
        "values": [
            {
                "role": "system",
                "content": "=Tu es le Designer visuel de Kilatech.ma. MISSION : Optimiser l'apparence de l'article et surtout de l'IMAGE DU PRODUIT."
            },
            {
                "role": "user",
                "content": "=DONNÉES : {{ $json.text }}\nURL IMAGE : {{ $(\"PRODUIT\").first().json.images[0]?.url || \"https://api.kilatech.ma/storage/placeholder.jpg\" }}\n\nCONSIGNES :\n1. Crée une balise ALT SEO parfaite pour cette image.\n2. Définis le style CSS Premium.\n3. Instructions de mise en page.\n\nRéponds UNIQUEMENT en JSON : {\"image_alt\": \"...\", \"css_style\": \"...\", \"layout_instructions\": \"...\"}"
            }
        ]
    };
    // Remove old parameters
    delete designerNode.parameters.prompt;
}

fs.writeFileSync('WORKFLOW_DESIGNER_CHAT_MODE.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_DESIGNER_CHAT_MODE.json created');
