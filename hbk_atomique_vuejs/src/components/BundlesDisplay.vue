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
          <ul class="col">
            <li v-for="message in tab.messages" :key="message.id" :value="message.id" :class="[message.status ? '' : 'text-danger']">{{ message.content }}</li>
          </ul>
          <div class="row">
            <div class="col">
              <h6>Les champs au niveau de D7</h6>
              <ul>
                <li v-for="field in tab.fields.d7" :key="field.id" :value="field.id" :class="[field.is_created ? 'text-success' : 'text-danger']">
                  {{ field.label }} <i>({{ field.id }})</i>
                </li>
              </ul>
            </div>
            <div class="col">
              <h6>Les champs au niveau de D10</h6>
              <ul>
                <li v-for="field in tab.fields.d10" :key="field.id" :value="field.id" :class="[field.is_new_creation ? 'text-info' : 'text-success']">
                  {{ field.label }} <i>({{ field.id }})</i>
                </li>
              </ul>
            </div>
          </div>
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
//import Dialog from "primevue/dialog";
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
      bundles.items.push({ title: bundle.label, content: bundle, id: j, show_json: false, messages: [], fields: { d10: [], d7: [], errors: [] } });
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
        config.post("http://you-v10.kksa/admin/migration-hbk-auto/manage-config", datas).then((resultD10) => {
          console.log("D10  : ", resultD10);
          if (resultD10.data) {
            analysisFields(tab, resultD10.data.fields.value, resultD10.data.fields.errors, result.data[tab.id].fields);
            tab.messages = [];
            for (var i in resultD10.data) {
              const item = resultD10.data[i];
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
};

/**
 * --
 * @param fieldsD10
 * @param notDefineFields
 * @param fieldsD7
 */
const analysisFields = (tab, fieldsD10, notDefineFields, fieldsD7) => {
  tab.fields.d10 = [];
  tab.fields.d7 = [];
  tab.fields.errors = [];
  for (var i in fieldsD10) {
    const field = fieldsD10[i];
    tab.fields.d10.push({ label: field.field_config.label, id: field.id, is_new_creation: fieldsD7[i] ? false : true });
  }

  for (var j in notDefineFields) {
    const field = notDefineFields[j];
    tab.fields.errors.push({ label: field.label, id: j });
  }

  for (var k in fieldsD7) {
    const field = fieldsD7[k];
    tab.fields.d7.push({ label: field.label, id: k, is_created: fieldsD10[k] ? true : false });
  }
};
// https://vuejs.org/api/#composition-api
</script>
