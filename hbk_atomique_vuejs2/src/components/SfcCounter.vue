<template>
  <Button severity="secondary" @click="increment">Test prime button : {{ count }}</Button>
</template>
<script setup>
import { ref, onMounted } from "vue";
import Button from "primevue/button";
// declarration de proprité.
const count = ref(0);

// hooks de cycle de vie
onMounted(() => {
  console.log(`La valeur initiale de count est ${count.value}.`);
});

// fonctions qui modifient l'état et déclenchent des mises à jour
function increment() {
  count.value++;
}
</script>
