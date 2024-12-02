<template>
  <!--
Ce fichier permet d'affichager toutes les configurations.
-->
  <div class="card" :style="{ 'max-width': '1200px', padding: '0.5rem' }">
    <Button :label="numbersBundles" @click="buildBundle" />
    <Accordion value="0">
      <AccordionPanel v-for="tab in bundles.items" :key="tab.id" :value="tab.id">
        <AccordionHeader>
          <div>
            {{ tab.title }} <i>({{ tab.id }})</i>
          </div>
        </AccordionHeader>
        <AccordionContent>
          <Button label="Verifier la configuration" @click="CheckConfig(tab)" />
          <Dialog v-model:visible="tab.show_json" maximizable modal :header="tab.title" :style="{ width: '50rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <pre :style="{ 'font-size': '10px' }">{{ tab.content }}</pre>
          </Dialog>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>
  </div>
</template>

<script setup>
import { reactive, defineProps, computed } from "vue";
import config from "../rootConfig";
import Accordion from "primevue/accordion";
import AccordionPanel from "primevue/accordionpanel";
import AccordionHeader from "primevue/accordionheader";
import AccordionContent from "primevue/accordioncontent";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

const props = defineProps(["bundles"]);

/**
 * Contient les definitions des
 */
const bundles = reactive({ items: [] });

const numbersBundles = computed(() => {
  if (props.bundles) {
    return "List bundles (" + Object.keys(props.bundles).length + ")";
  }
  return "Aucun bundle";
});

const buildBundle = () => {
  if (props.bundles) {
    bundles.items = [];
    for (var j in props.bundles) {
      const bundle = props.bundles[j];
      bundles.items.push({ title: bundle.label, content: bundle, id: j, show_json: false });
    }
  }
};
/**
 * Permet de verifier la configuration.
 *
 */
const CheckConfig = (tab) => {
  config.get("/migrateexport/migrate-export-entities/" + tab.id).then((result) => {
    console.log("result bundles : ", result);
  });
  //
};

// https://vuejs.org/api/#composition-api
</script>
