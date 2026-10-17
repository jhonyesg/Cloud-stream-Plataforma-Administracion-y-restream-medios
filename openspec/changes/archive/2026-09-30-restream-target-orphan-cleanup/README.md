# restream-target-orphan-cleanup

Limpia las filas huérfanas que deja el rollback de store() cuando createPlatformBroadcast falla, y deja de bloquear re-creaciones legítimas en el validador de triplete duplicado.
