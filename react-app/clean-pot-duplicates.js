const fs = require('fs');
const path = require('path');

class PotCleaner {
    constructor() {
        this.potFile = path.join(__dirname, '..', 'languages', 'shopmetrics-analytics.pot');
    }

    cleanDuplicates() {
        console.log('Reading POT file...');
        const content = fs.readFileSync(this.potFile, 'utf-8');
        
        // Split into lines for processing
        const lines = content.split('\n');
        const cleanedLines = [];
        const seenMsgids = new Set();
        const currentEntry = [];
        let duplicateCount = 0;
        let isInEntry = false;
        let currentMsgid = null;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            
            // Check if this is a comment line starting an entry
            if (line.startsWith('#:') || line.startsWith('#.') || line.startsWith('#,')) {
                if (isInEntry && currentMsgid && seenMsgids.has(currentMsgid)) {
                    // Skip this duplicate entry
                    duplicateCount++;
                    currentEntry.length = 0;
                    currentMsgid = null;
                    isInEntry = false;
                    continue;
                }
                
                // Start new entry
                if (currentEntry.length > 0) {
                    cleanedLines.push(...currentEntry);
                    cleanedLines.push(''); // Add empty line between entries
                }
                currentEntry.length = 0;
                currentEntry.push(line);
                isInEntry = true;
            } 
            // Check for msgid line
            else if (line.startsWith('msgid ')) {
                const msgidMatch = line.match(/msgid "([^"]+)"/);
                if (msgidMatch) {
                    currentMsgid = msgidMatch[1];
                    if (seenMsgids.has(currentMsgid)) {
                        // This is a duplicate - skip entire entry
                        duplicateCount++;
                        currentEntry.length = 0;
                        currentMsgid = null;
                        isInEntry = false;
                        continue;
                    } else {
                        seenMsgids.add(currentMsgid);
                    }
                }
                currentEntry.push(line);
            }
            // Check for msgstr line
            else if (line.startsWith('msgstr ')) {
                currentEntry.push(line);
                // Entry complete
                if (currentMsgid && !seenMsgids.has(currentMsgid)) {
                    seenMsgids.add(currentMsgid);
                }
                isInEntry = false;
            }
            // Empty line or other content
            else if (line.trim() === '') {
                if (currentEntry.length > 0) {
                    cleanedLines.push(...currentEntry);
                    cleanedLines.push('');
                    currentEntry.length = 0;
                }
                currentMsgid = null;
                isInEntry = false;
            }
            // Header or other content
            else {
                if (!isInEntry) {
                    cleanedLines.push(line);
                } else {
                    currentEntry.push(line);
                }
            }
        }

        // Add final entry if exists
        if (currentEntry.length > 0) {
            cleanedLines.push(...currentEntry);
        }

        // Write cleaned content
        const cleanedContent = cleanedLines.join('\n');
        fs.writeFileSync(this.potFile, cleanedContent);
        
        console.log(`POT file cleaned: ${duplicateCount} duplicate entries removed`);
        console.log(`Total unique msgids: ${seenMsgids.size}`);
        
        return {
            duplicatesRemoved: duplicateCount,
            uniqueStrings: seenMsgids.size
        };
    }
}

if (require.main === module) {
    const cleaner = new PotCleaner();
    cleaner.cleanDuplicates();
}

module.exports = PotCleaner; 