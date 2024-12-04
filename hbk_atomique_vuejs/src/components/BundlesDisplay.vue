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
          <ul>
            <li v-for="message in tab.messages" :key="message.id" :value="message.id" :class="[message.status ? '' : 'text-danger']">{{ message.content }}</li>
          </ul>
          <Dialog v-model:visible="tab.show_json" maximizable modal :header="tab.title" :style="{ width: '50rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <pre :style="{ 'font-size': '10px' }">{{ tab.content }}</pre>
          </Dialog>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>
  </div>
  <Toast />
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
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

const props = defineProps(["bundles", "base_table", "bundle_key"]);

/**
 * Contient les definitions des
 */
const bundles = reactive({ items: [] });
const toast = useToast();

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
      bundles.items.push({ title: bundle.label, content: bundle, id: j, show_json: false, messages: [] });
    }
  }
};
/**
 * Permet de verifier la configuration.
 *
 */
const CheckConfig = (tab) => {
  config
    .get("/migrateexport/migrate-export-entities/" + props.base_table + "/" + tab.id)
    .then((result) => {
      if (result.data) {
        const datas = { config_id: props.base_table + "." + props.bundle_key + "." + tab.id, datas: result.data[tab.id] ? result.data[tab.id] : result.data };
        config.post("http://you-v10.kksa/admin/migration-hbk-auto/manage-config", datas).then((result) => {
          console.log("D10  : ", result);
          if (result.data) {
            tab.messages = [];
            for (var i in result.data) {
              const item = result.data[i];
              tab.messages.push({ content: item.note, id: i, status: item.status, value: item.status });
            }
          }
        });
      } else {
        toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 5000 });
      }
    })
    .catch((er) => {
      console.log("er : ", er);
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 5000 });
    });
  //
};

// https://vuejs.org/api/#composition-api
</script>
