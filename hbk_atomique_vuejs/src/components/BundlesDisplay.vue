<template>
  <!--
Ce fichier permet d'affichager toutes les configurations.
-->
  <div class="card bundles-display" :style="{ 'max-width': '1200px', padding: '0.5rem' }">
    <Button :label="numbersBundles" @click="buildBundle" />
    <Accordion v-if="bundles.items.length" value="0" class="my-3">
      <AccordionPanel v-for="tab in bundles.items" :key="tab.id" :value="tab.id">
        <AccordionHeader class="bg-secondary-subtle py-2 border-0 border-secondary-subtle border-bottom">
          <div>
            {{ tab.title }} <i>({{ tab.id }})</i>
          </div>
        </AccordionHeader>
        <AccordionContent>
          <!-- Analyse de configuration -->
          <div class="row mb-3">
            <div class="col">
              <Button label="Verifier la configuration" severity="secondary" @click="CheckConfig(tab)" />
            </div>
            <div v-if="tab.fields.errors && tab.fields.errors.length" class="col">
              <Button label="Creer les champs manquant" severity="info" @click="CreateFieldsNotExist(tab)" />
            </div>
            <div v-if="tab.fields.d7 && tab.fields.d7.length" class="col">
              <Button label="Re-creer tous les champs" severity="warn" @click="ReCreateAllFields(tab)" />
            </div>
          </div>
          <ul class="col">
            <li v-for="message in tab.messagesConfig" :key="message.id" :value="message.id" :class="[message.status ? '' : 'text-danger']">{{ message.content }}</li>
          </ul>
          <div v-if="tab.fields.d7.length" class="row mb-3">
            <div class="col">
              <h6>Les champs au niveau de D7</h6>
              <ul>
                <li v-for="field in tab.fields.d7" :key="field.id" :value="field.id" :class="[field.is_created ? 'text-success' : 'text-danger']">
                  {{ field.label }} <i class="small">( fieldname: {{ field.id }}, type: {{ field.type_field }} )</i>
                </li>
              </ul>
            </div>
            <div v-if="tab.fields.d10.length" class="col">
              <h6>Les champs au niveau de D10</h6>
              <ul>
                <li v-for="field in tab.fields.d10" :key="field.id" :value="field.id" :class="[field.is_new_creation ? 'text-info' : 'text-success']">
                  {{ field.label }} <i>({{ field.id }})</i>
                </li>
              </ul>
            </div>
          </div>
          <!-- Affiche le retour lors de la creation des champs.  -->
          <div v-if="tab.messagesFields.length">
            <hr />
            <Accordion value="field_info">
              <AccordionPanel v-for="sub_tab in tab.messagesFields" :key="sub_tab.id" :value="sub_tab.id">
                <AccordionHeader class="bg-light-subtle py-1 fw-normal">
                  <div :class="[sub_tab.status ? 'text-success' : 'text-danger']">{{ sub_tab.label }}</div>
                </AccordionHeader>
                <AccordionContent :style="{ 'font-size': '13px' }">
                  <h5>Messages</h5>
                  <pre v-html="sub_tab.content"></pre>
                  <h5>Field storage config</h5>
                  <pre>{{ sub_tab.field_storage_config }}</pre>
                  <h5>Field config</h5>
                  <pre>{{ sub_tab.field_config }}</pre>
                </AccordionContent>
              </AccordionPanel>
            </Accordion>
          </div>
          <!-- Analyse des contenus -->
          <div v-if="tab.fields.errors && tab.fields.errors.length == 0 && tab.fields.d10 && tab.fields.d10.length">
            <hr />
            <div class="row">
              <div class="col">
                <Button label="Importer les contenus manquant" severity="success" @click="ImportContentNotExit(tab)" />
              </div>
              <div class="col">
                <Button label="Re-importer les contenus" severity="info" @click="ReImportAllContent(tab)" />
              </div>
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
      bundles.items.push({
        title: bundle.label,
        content: bundle,
        id: j,
        show_json: false,
        messagesConfig: [],
        messagesFields: [],
        fields: { d10: [], d7: [], errors: [], extra_fields: [] },
      });
    }
  }
};

/**
 * Permet de verifier la configuration.
 *
 */
const CheckConfig = (tab) => {
  //reset datas :
  tab.fields.d10 = [];
  tab.fields.d7 = [];
  tab.fields.errors = [];
  tab.fields.extra_fields = [];
  tab.messagesConfig = [];
  tab.messagesFields = [];

  config
    .get("/migrateexport/migrate-export-entities/" + props.base_table + "/" + tab.id)
    .then((result) => {
      if (result.data) {
        const datas = { config_id: props.base_table + "." + props.bundle_key + "." + tab.id, datas: result.data[tab.id] ? result.data[tab.id] : result.data };
        config.post("http://you-v10.kksa/admin/migration-hbk-auto/manage-config", datas).then((resultD10) => {
          console.log("D10  : ", resultD10);
          if (resultD10.data) {
            analysisFields(tab, resultD10.data.fields.value, resultD10.data.fields.errors, result.data[tab.id].fields, result.data[tab.id].extra_fields);
            for (var i in resultD10.data) {
              const item = resultD10.data[i];
              tab.messagesConfig.push({ content: item.note, id: i, status: item.status, value: item.status });
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
const analysisFields = (tab, fieldsD10, notDefineFields, fieldsD7, extra_fields) => {
  for (var i in fieldsD10) {
    const field = fieldsD10[i];
    tab.fields.d10.push({ label: field.field_config.label, id: field.id, is_new_creation: fieldsD7[i] ? false : true, field_config: field.field_config });
  }

  for (var j in notDefineFields) {
    const field = notDefineFields[j];
    tab.fields.errors.push({ label: field.label, id: j, value: field });
  }

  for (var k in fieldsD7) {
    const field = fieldsD7[k];
    tab.fields.d7.push({ label: field.label, id: k, is_created: fieldsD10[k] ? true : false, type_field: field.field_type.type });
  }

  for (var d in extra_fields) {
    const field = extra_fields[d];
    tab.fields.extra_fields.push({ label: field.label, id: d, content: field });
  }
};

const CreateFieldsNotExist = (tab) => {
  tab.messagesFields = [];
  config
    .post("http://you-v10.kksa/admin/migration-hbk-auto/generate-fields", {
      fields: tab.fields.errors,
      entity_type: props.base_table,
      bundle_key: props.bundle_key,
      bundle: tab.id,
    })
    .then((result) => {
      console.log("result : ", result);
      if (result.data) {
        for (var i in result.data) {
          const field = result.data[i];
          tab.messagesFields.push({
            label: i,
            id: i,
            content: field.note,
            status: field.status,
            field_config: field.field_config,
            field_storage_config: field.field_storage_config,
          });
        }
        // CheckConfig(tab);
      }
    })
    .catch((er) => {
      console.log("er : ", er);
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 5000 });
    });
};
const ReCreateAllFields = (tab) => {
  //
  console.log("tab : ", tab);
};
const ImportContentNotExit = (tab) => {
  // Cette approche est centre d'avantage sur les nodes.
  config
    .get("/migrateexport/export-import-entities/load-entitties/" + props.base_table + "/" + tab.id + "/0/10")
    .then((reult) => {
      if (reult.data) {
        for (var id in reult.data) {
          buildAndCreateEntity(reult.data[id], tab);
        }
      }
    })
    .catch((er) => {
      console.log("er : ", er);
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 5000 });
    });
};

/**
 * Il faudra eviter d'envoyer une masse importante de données.
 * @param entity
 * @param tab
 */
const buildAndCreateEntity = async (entity, tab) => {
  const retriveDataInField = (fieldD7) => {
    const datas = [];
    if (fieldD7.und) {
      fieldD7.und.forEach((item) => {
        var data = {};
        if (item.value) data.value = item.value;
        if (item.target_id) data.target_id = item.target_id;
        if (item.fid) data.fid = item.fid;
        if (item.alt) data.alt = item.alt;
        datas.push(data);
      });
    } else {
      toast.add({ severity: "error", summary: "Impossible de recuperer les données", detail: "Message Content", life: 5000 });
      throw new Error("Impossible de recuperer les données");
    }
    return datas;
  };
  const values = {
    title: entity.title,
    nid: entity.nid,
    uid: entity.uid,
    type: entity.type,
    created: entity.created,
    changed: entity.changed,
    language: entity.language,
    status: entity.status,
  };
  tab.fields.d10.forEach((field) => {
    if (entity[field.field_config.field_name]) values[field.field_config.field_name] = retriveDataInField(entity[field.field_config.field_name], field.field_config);
  });
  //
  console.log("values : ", values);
  return config.post("http://you-v10.kksa/apivuejs/save-entity/" + props.base_table, values).then((result) => {
    console.log("result : ", result);
  });
};
const ReImportAllContent = (tab) => {
  //
  console.log("tab : ", tab);
};
// https://vuejs.org/api/#composition-api
</script>
<style lang="scss">
.bundles-display {
  em.placeholder {
    color: #ab2a2a;
  }
}
</style>
