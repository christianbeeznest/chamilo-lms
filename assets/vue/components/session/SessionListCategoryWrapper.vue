<template>
  <div
    v-if="sessions.length > 0"
    class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4"
  >
    <div
      v-for="session in sessions"
      :key="session.id"
    >
      <SessionCardSimple :session="session" />
    </div>
  </div>
</template>

<script setup>
import SessionCardSimple from "./SessionCardSimple.vue"

defineProps({
  sessions: {
    type: Array,
    required: true,
  },
})
</script>
