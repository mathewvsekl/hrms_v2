const fs = require('fs');
const path = require('path');

const directory = path.join(__dirname, 'frontend/src/pages');

function patchFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // Pattern 1: doc.file_path.startsWith('http') ? doc.file_path : `${api.defaults.baseURL.replace('/api', '')}${doc.file_path}`
    content = content.replace(/\b(\w+)\.file_path\.startsWith\('http'\)\s*\?\s*\1\.file_path\s*:\s*`\$\{api\.defaults\.baseURL\.replace\('\/api',\s*''\)\}\$\{\1\.file_path\}`/g, 
        (match, varName) => `getSecureMediaUrl(${varName}.file_path)`);

    // Pattern 2: `${api.defaults.baseURL.replace('/api', '')}${doc.file_path}`
    content = content.replace(/`\$\{api\.defaults\.baseURL\.replace\('\/api',\s*''\)\}\$\{(\w+)\.file_path\}`/g, 
        (match, varName) => `getSecureMediaUrl(${varName}.file_path)`);

    // Pattern 3: href={doc.file_path}
    content = content.replace(/href=\{([A-Za-z0-9_]+)\.file_path\}/g, 
        (match, varName) => `href={getSecureMediaUrl(${varName}.file_path)}`);

    // Pattern 4: previewDoc?.file_path?.startsWith('http') ? previewDoc.file_path : `${api.defaults.baseURL...}`
    content = content.replace(/([A-Za-z0-9_]+)\?\.file_path\?\.startsWith\('http'\)\s*\?\s*\1\.file_path\s*:\s*`\$\{api\.defaults\.baseURL\.replace\('\/api',\s*''\)\}\$\{\1\?\.file_path\}`/g, 
        (match, varName) => `getSecureMediaUrl(${varName}?.file_path)`);
        
    // Leave requests: attachment_path
    content = content.replace(/href=\{([A-Za-z0-9_]+)\.attachment_path\}/g, 
        (match, varName) => `href={getSecureMediaUrl(${varName}.attachment_path)}`);

    if (content !== original) {
        // Ensure import exists
        if (!content.includes("import { getSecureMediaUrl } from '../utils/mediaHelper'")) {
            content = "import { getSecureMediaUrl } from '../utils/mediaHelper';\n" + content;
        }
        fs.writeFileSync(filePath, content);
        console.log(`Patched: ${path.basename(filePath)}`);
    }
}

const files = fs.readdirSync(directory);
for (const file of files) {
    if (file.endsWith('.jsx')) {
        patchFile(path.join(directory, file));
    }
}
