#!/bin/bash
# Build script to generate .mo files from .po files for WordPress plugin translations

echo "Building translation files..."

cd languages

# Generate .mo files from .po files using msgfmt
echo "Generating .mo files..."

msgfmt llm-visibility-monitor-de_DE.po -o llm-visibility-monitor-de_DE.mo
if [ $? -eq 0 ]; then
    echo "✓ Generated llm-visibility-monitor-de_DE.mo"
else
    echo "✗ Failed to generate llm-visibility-monitor-de_DE.mo"
fi

msgfmt llm-visibility-monitor-de_DE_formal.po -o llm-visibility-monitor-de_DE_formal.mo
if [ $? -eq 0 ]; then
    echo "✓ Generated llm-visibility-monitor-de_DE_formal.mo"
else
    echo "✗ Failed to generate llm-visibility-monitor-de_DE_formal.mo"
fi

msgfmt llm-visibility-monitor-de_CH.po -o llm-visibility-monitor-de_CH.mo
if [ $? -eq 0 ]; then
    echo "✓ Generated llm-visibility-monitor-de_CH.mo"
else
    echo "✗ Failed to generate llm-visibility-monitor-de_CH.mo"
fi

msgfmt llm-visibility-monitor-de_CH_informal.po -o llm-visibility-monitor-de_CH_informal.mo
if [ $? -eq 0 ]; then
    echo "✓ Generated llm-visibility-monitor-de_CH_informal.mo"
else
    echo "✗ Failed to generate llm-visibility-monitor-de_CH_informal.mo"
fi

echo "Translation build completed!"
echo "Files generated:"
ls -la llm-visibility-monitor-*.mo
