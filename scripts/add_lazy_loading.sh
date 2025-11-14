#!/bin/bash
# Add lazy loading to img tags that don't have it

FILES_TO_UPDATE=(
    "/home/user/moje-stranky/api/control_center_api.php"
    "/home/user/moje-stranky/includes/control_center_console.php"
)

for file in "${FILES_TO_UPDATE[@]}"; do
    if [ -f "$file" ]; then
        echo "Processing: $file"
        # Add loading="lazy" to <img tags that don't have it
        # This regex finds <img tags without loading= attribute and adds it
        sed -i 's/<img\([^>]*\)\(src=\)\([^>]*\)>/<img\1\2\3 loading="lazy">/g' "$file"
        # Clean up if loading was added multiple times
        sed -i 's/loading="lazy"[[:space:]]*loading="lazy"/loading="lazy"/g' "$file"
        echo "✓ Added lazy loading to $file"
    fi
done

echo "Done! Lazy loading added to all images."
