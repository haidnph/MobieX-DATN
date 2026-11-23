import { describe, expect, it } from 'vitest'
import { clearInventoryOperationKey, getInventoryOperationKey } from '../features/admin/inventoryOperationKey'

function createStorage() {
  const entries = new Map()
  return {
    getItem: (key) => entries.get(key) ?? null,
    setItem: (key, value) => entries.set(key, value),
    removeItem: (key) => entries.delete(key),
  }
}

describe('inventory operation idempotency keys', () => {
  it('reuses a key when the same operation payload is retried', () => {
    const storage = createStorage()
    const payload = { product_variant_id: 12, quantity: 4, note: 'Restock' }
    const first = getInventoryOperationKey('import', payload, storage, () => 'key-1')
    const retry = getInventoryOperationKey('import', payload, storage, () => 'key-2')

    expect(retry.key).toBe(first.key)
    expect(retry.storageKey).toBe(first.storageKey)
  })

  it('uses separate keys for changed payloads and clears a completed operation', () => {
    const storage = createStorage()
    const first = getInventoryOperationKey('export', { product_variant_id: 12, quantity: 1 }, storage, () => 'key-1')
    const changed = getInventoryOperationKey('export', { product_variant_id: 12, quantity: 2 }, storage, () => 'key-2')

    expect(changed.key).not.toBe(first.key)
    clearInventoryOperationKey(first.storageKey, storage)
    expect(getInventoryOperationKey('export', { product_variant_id: 12, quantity: 1 }, storage, () => 'key-3').key).toBe('key-3')
  })
})
