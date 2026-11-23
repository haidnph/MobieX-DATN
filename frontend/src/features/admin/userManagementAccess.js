function roleSlugs(user) {
  return (user?.roles ?? [])
    .map((role) => (typeof role === 'string' ? role : role?.slug))
    .filter(Boolean)
}

function permissionSlugs(user) {
  return (user?.permissions ?? [])
    .map((permission) => (typeof permission === 'string' ? permission : permission?.slug))
    .filter(Boolean)
}

export function canManageUserAccount(actor, target) {
  return !roleSlugs(target).includes('super-admin') || roleSlugs(actor).includes('super-admin')
}

export function canAssignUserRole(actor, role) {
  if (roleSlugs(actor).includes('super-admin')) return true
  if (!role || role.slug === 'super-admin') return false

  const heldPermissions = new Set(permissionSlugs(actor))
  const requiredPermissions = (role.permissions ?? [])
    .map((permission) => (typeof permission === 'string' ? permission : permission?.slug))
    .filter(Boolean)

  return requiredPermissions.every((permission) => heldPermissions.has(permission))
}
