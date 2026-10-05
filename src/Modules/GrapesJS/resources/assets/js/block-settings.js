/**
 * Resolve the permanent block instance ID used by serialized page data.
 *
 * Newly dropped blocks initially use their block slug as a temporary ID. The
 * storage serializer replaces that slug with a generated ID, so the serialized
 * block key is authoritative whenever the temporary ID is not present.
 */
export function resolveSerializedBlockId(currentBlockId, blocks) {
    if (! blocks || typeof blocks !== 'object' || Array.isArray(blocks)) {
        return null;
    }

    if (typeof currentBlockId === 'string' &&
        Object.prototype.hasOwnProperty.call(blocks, currentBlockId)) {
        return currentBlockId;
    }

    const serializedBlockIds = Object.keys(blocks);
    return serializedBlockIds.length === 1 ? serializedBlockIds[0] : null;
}
