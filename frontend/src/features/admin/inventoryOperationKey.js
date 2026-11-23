const STORAGE_PREFIX = 'mobiex:inventory'

export function getInventoryOperationKey(action, payload, storage = globalThis.sessionStorage, createId = () => globalThis.crypto.randomUUID()) {
  const fingerprint = encodeURIComponent(JSON.stringify(payload))
  const storageKey = `${STORAGE_PREFIX}:${action}:${fingerprint}`
  let key = storage.getItem(storageKey)

  if (!key) {
    key = createId()
    storage.setItem(storageKey, key)
  }

  return { key, storageKey }
}

export function clearInventoryOperationKey(storageKey, storage = globalThis.sessionStorage) {
  storage.removeItem(storageKey)
}
