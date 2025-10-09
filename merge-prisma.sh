# merge-prisma.sh
PRISMA_DIR=prisma
TMP="$PRISMA_DIR/_merged.prisma"

cp "$PRISMA_DIR/schema.prisma" "$TMP"          # datasource + generators only
cat "$PRISMA_DIR"/schema/*.prisma >> "$TMP"    # append all model files

npx prisma generate --schema="$TMP"
rm "$TMP"