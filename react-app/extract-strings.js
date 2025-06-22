const fs = require('fs');
const path = require('path');

class ReactStringExtractor {
    constructor() {
        this.srcDir = path.join(__dirname, 'src');
        this.potFile = path.join(__dirname, '..', 'languages', 'shopmetrics.pot');
        this.languagesDir = path.join(__dirname, '..', 'languages');
        this.buildDir = path.join(__dirname, 'build', 'static', 'js');
        this.extractedStrings = new Map();
    }

    extractStrings() {
        console.log('Scanning directory:', this.srcDir);
        this.scanDirectory(this.srcDir);
        console.log('Extracted', this.extractedStrings.size, 'unique strings');
    }

    scanDirectory(dir) {
        const items = fs.readdirSync(dir);
        for (const item of items) {
            const fullPath = path.join(dir, item);
            const stat = fs.statSync(fullPath);
            if (stat.isDirectory()) {
                this.scanDirectory(fullPath);
            } else if (/\.(js|jsx|ts|tsx)$/.test(item)) {
                this.extractFromFile(fullPath);
            }
        }
    }

    extractFromFile(filePath) {
        const content = fs.readFileSync(filePath, 'utf-8');
        const relativePath = path.relative(path.join(__dirname, '..'), filePath);
        
        // Match __('string')
        const regex = /__\(\s*['"]([^'"]+)['"]/g;
        let match;
        while ((match = regex.exec(content)) !== null) {
            const string = match[1];
            const lineNumber = content.substring(0, match.index).split('\n').length;
            
            if (!this.extractedStrings.has(string)) {
                this.extractedStrings.set(string, []);
            }
            this.extractedStrings.get(string).push({
                file: relativePath,
                line: lineNumber
            });
        }
    }

    updatePotFile() {
        let potContent = fs.readFileSync(this.potFile, 'utf-8');
        
        // Get existing msgids to avoid duplicates
        const existingMsgids = this.getExistingMsgids(potContent);
        
        // Remove existing React section
        potContent = potContent.replace(/\n# React Components Translations.*$/s, '');
        
        // Filter out strings that already exist in POT file
        const newStrings = new Map();
        let duplicateCount = 0;
        
        for (const [string, locations] of this.extractedStrings) {
            if (!existingMsgids.has(string)) {
                newStrings.set(string, locations);
            } else {
                duplicateCount++;
            }
        }
        
        // Add new React section only with unique strings
        if (newStrings.size > 0) {
            let reactSection = '\n\n# React Components Translations\n\n';
            for (const [string, locations] of newStrings) {
                for (const location of locations) {
                    reactSection += `#: ${location.file}:${location.line}\n`;
                }
                reactSection += `msgid ${this.formatPotString(string)}\n`;
                reactSection += `msgstr ""\n\n`;
            }
            potContent += reactSection;
        }
        
        fs.writeFileSync(this.potFile, potContent);
        console.log(`Updated POT file: ${newStrings.size} new strings added, ${duplicateCount} duplicates skipped`);
    }

    /**
     * Format string for POT file with proper escaping and line wrapping
     */
    formatPotString(str) {
        // Escape quotes and backslashes
        let escaped = str.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        
        // Handle newlines
        escaped = escaped.replace(/\n/g, '\\n');
        
        // If string is longer than 80 characters, split into multiple lines
        if (escaped.length > 80) {
            const lines = [];
            let currentLine = '';
            const words = escaped.split(' ');
            
            for (const word of words) {
                if (currentLine.length + word.length + 1 > 80 && currentLine.length > 0) {
                    lines.push(`"${currentLine}"`);
                    currentLine = word;
                } else {
                    currentLine += (currentLine.length > 0 ? ' ' : '') + word;
                }
            }
            
            if (currentLine.length > 0) {
                lines.push(`"${currentLine}"`);
            }
            
            if (lines.length > 1) {
                return '""' + '\n' + lines.join('\n');
            }
        }
        
        return `"${escaped}"`;
    }

    /**
     * Extract existing msgids from POT content to avoid duplicates
     */
    getExistingMsgids(potContent) {
        const existingMsgids = new Set();
        const regex = /msgid "([^"]+)"/g;
        let match;
        
        while ((match = regex.exec(potContent)) !== null) {
            existingMsgids.add(match[1]);
        }
        
        return existingMsgids;
    }

    /**
     * Format string for POT file with proper escaping and line wrapping
     */
    formatPotString(str) {
        // Escape quotes and backslashes
        let escaped = str.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        
        // Handle newlines
        escaped = escaped.replace(/\n/g, '\\n');
        
        // If string is longer than 80 characters, split into multiple lines
        if (escaped.length > 80) {
            const lines = [];
            let currentLine = '';
            const words = escaped.split(' ');
            
            for (const word of words) {
                if (currentLine.length + word.length + 1 > 80 && currentLine.length > 0) {
                    lines.push(`"${currentLine}"`);
                    currentLine = word;
                } else {
                    currentLine += (currentLine.length > 0 ? ' ' : '') + word;
                }
            }
            
            if (currentLine.length > 0) {
                lines.push(`"${currentLine}"`);
            }
            
            if (lines.length > 1) {
                return '""' + '\n' + lines.join('\n');
            }
        }
        
        return `"${escaped}"`;
    }

    /**
     * Generate JSON translation files for built main.js files
     */
    generateTranslationFiles() {
        if (!fs.existsSync(this.buildDir)) {
            console.log('Build directory not found, skipping JSON generation');
            return;
        }

        // Find main.*.js files in build directory
        const files = fs.readdirSync(this.buildDir);
        const mainFiles = files.filter(file => file.match(/^main\.[a-f0-9]+\.js$/));
        
        if (mainFiles.length === 0) {
            console.log('No main.*.js files found in build directory');
            return;
        }

        // Clean up old translation JSON files
        this.cleanupOldTranslations();

        // Generate new JSON files for each locale
        const locales = this.getAvailableLocales();
        
        for (const locale of locales) {
            const translations = this.parsePoFile(locale);
            if (Object.keys(translations).length === 0) {
                console.log(`No translations found for locale: ${locale}`);
                continue;
            }

            for (const mainFile of mainFiles) {
                this.createJsonFile(locale, mainFile, translations);
            }
        }
    }

    /**
     * Clean up old translation JSON files
     */
    cleanupOldTranslations() {
        if (!fs.existsSync(this.languagesDir)) return;

        const files = fs.readdirSync(this.languagesDir);
        const jsonFiles = files.filter(file => 
            file.match(/^shopmetrics-[a-z]{2}_[A-Z]{2}-main\.[a-f0-9]+\.json$/)
        );

        for (const file of jsonFiles) {
            const filePath = path.join(this.languagesDir, file);
            fs.unlinkSync(filePath);
            console.log(`Removed old translation file: ${file}`);
        }
    }

    /**
     * Get available locales from existing PO files
     */
    getAvailableLocales() {
        if (!fs.existsSync(this.languagesDir)) return [];

        const files = fs.readdirSync(this.languagesDir);
        const poFiles = files.filter(file => file.match(/^shopmetrics-([a-z]{2}_[A-Z]{2})\.po$/));
        
        return poFiles.map(file => {
            const match = file.match(/^shopmetrics-([a-z]{2}_[A-Z]{2})\.po$/);
            return match ? match[1] : null;
        }).filter(Boolean);
    }

    /**
     * Parse PO file to extract translations
     */
    parsePoFile(locale) {
        const poFile = path.join(this.languagesDir, `shopmetrics-${locale}.po`);
        
        if (!fs.existsSync(poFile)) {
            return {};
        }

        const content = fs.readFileSync(poFile, 'utf-8');
        const translations = {};
        
        // Split content into blocks separated by empty lines
        const blocks = content.split('\n\n');
        
        for (const block of blocks) {
            const lines = block.trim().split('\n');
            let msgid = '';
            let msgstr = '';
            let currentSection = null;
            
            for (const line of lines) {
                if (line.startsWith('msgid ')) {
                    currentSection = 'msgid';
                    const match = line.match(/^msgid\s+"(.*)"/);
                    msgid = match ? match[1] : '';
                } else if (line.startsWith('msgstr ')) {
                    currentSection = 'msgstr';
                    const match = line.match(/^msgstr\s+"(.*)"/);
                    msgstr = match ? match[1] : '';
                } else if (line.startsWith('"') && line.endsWith('"')) {
                    // Continuation line
                    const content = line.slice(1, -1); // Remove quotes
                    if (currentSection === 'msgid') {
                        // Add space between concatenated strings if msgid is not empty
                        msgid += (msgid ? ' ' : '') + content;
                    } else if (currentSection === 'msgstr') {
                        // Add space between concatenated strings if msgstr is not empty
                        msgstr += (msgstr ? ' ' : '') + content;
                    }
                }
            }
            
            // Add translation if both msgid and msgstr are present and msgstr is not empty
            if (msgid && msgstr && msgstr !== msgid) {
                translations[msgid] = [msgstr];
            }
        }
        
        return translations;
    }

    /**
     * Create JSON translation file for specific locale and main file
     */
    createJsonFile(locale, mainFile, translations) {
        const jsonData = {
            domain: 'shopmetrics',
            locale_data: {
                'shopmetrics': {
                    '': {
                        domain: 'shopmetrics'
                    },
                    ...translations
                }
            }
        };

        // Remove .js extension from mainFile to create proper .json filename
        const baseFileName = mainFile.replace(/\.js$/, '');
        const jsonFileName = `shopmetrics-${locale}-${baseFileName}.json`;
        const jsonFilePath = path.join(this.languagesDir, jsonFileName);
        
        try {
            fs.writeFileSync(jsonFilePath, JSON.stringify(jsonData, null, 2));
            console.log(`Created translation file: ${jsonFileName} (${Object.keys(translations).length} translations)`);
        } catch (error) {
            console.error(`Failed to create ${jsonFileName}:`, error.message);
        }
    }

    run() {
        this.extractStrings();
        this.updatePotFile();
    }

    runPostBuild() {
        console.log('Generating translation JSON files for built React app...');
        this.generateTranslationFiles();
    }
}

if (require.main === module) {
    const extractor = new ReactStringExtractor();
    
    // Check if we should run post-build (generate JSON files)
    if (process.argv.includes('--post-build')) {
        extractor.runPostBuild();
    } else {
        extractor.run();
    }
}

module.exports = ReactStringExtractor;
