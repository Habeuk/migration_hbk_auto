<template>
  <!--
Ce fichier permet d'affichager toutes les configurations.
-->
  <div class="card" :style="{ 'max-width': '1200px', padding: '1rem' }">
    <Accordion value="0">
      <AccordionPanel v-for="tab in tabsEntitiesDefinitions.items" :key="tab.id" :value="tab.id">
        <AccordionHeader accordion.header.color="#f00">
          <div>
            {{ tab.title }} <i>({{ tab.id }})</i>
          </div>
        </AccordionHeader>
        <AccordionContent>
          <BundlesDisplay :bundles="tab.bundles" :base_table="tab.content['base table']" :entity_type_id="tab.id" :bundle_key="getBundleKey(tab)"></BundlesDisplay>
          <Button label="Afficher le code json" @click="openClose(tab)" />
          <Dialog v-model:visible="tab.show_json" maximizable modal :header="tab.title" :style="{ width: '50rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <pre :style="{ 'font-size': '10px' }">{{ tab.content }}</pre>
          </Dialog>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>
  </div>
  <Button severity="secondary" @click="loadAllEntities"> Load all entities </Button>
</template>

<script setup>
import { reactive } from "vue";
import config from "../rootConfig";
import Accordion from "primevue/accordion";
import AccordionPanel from "primevue/accordionpanel";
import AccordionHeader from "primevue/accordionheader";
import AccordionContent from "primevue/accordioncontent";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import BundlesDisplay from "./BundlesDisplay.vue";

/**
 * Contient les definitions des
 */
const tabsEntitiesDefinitions = reactive({ items: [] });

/**
 * Charge les definitions d'entites.
 */
function loadAllEntities() {
  config.get("/migrateexport/entities-definitions").then((result) => {
    if (result.data) {
      for (var i in result.data) {
        const definition = result.data[i];
        tabsEntitiesDefinitions.items.push({ title: definition.label, content: definition, id: i, show_json: false, bundles: definition.bundles });
      }
    }
  });
}

function openClose(tab) {
  tab.show_json = tab.show_json ? false : true;
}
const getBundleKey = (tab) => {
  return tab.content["bundle keys"] ? tab.content["bundle keys"]["bundle"] : tab.content["base table"];
};

// https://vuejs.org/api/#composition-api
</script>
