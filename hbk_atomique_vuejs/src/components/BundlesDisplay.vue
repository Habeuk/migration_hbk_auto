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
                <li v-for="field in tab.fields.d10" :key="field.id" :value="field.id" :class="[field.is_manuel_creation ? 'text-info' : 'text-success']">
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
          <!-- gestion de l'import des contenus -->
          <div v-if="tab.fields.errors && tab.fields.errors.length == 0 && tab.messagesConfig && tab.messagesConfig.length">
            <hr />
            <div class="row mb-3">
              <div class="col col-sm-3">
                <label class="font-bold block mb-2"> Pagination length </label>
                <InputNumber v-model="tab.pagination.length" fluid />
              </div>
              <div class="col col-sm-3">
                <label class="font-bold block mb-2"> Pagination start </label>
                <InputNumber v-model="tab.pagination.start" fluid />
              </div>
            </div>
            <div class="row">
              <div class="col">
                <div class="flex items-center gap-2">
                  <Checkbox v-model="tab.pagination.continue" binary :inputId="tab.id + '_continue'" />
                  <label :for="tab.id + '_continue'" class="text-sm">Continue</label>
                </div>
                <Button
                  :label="'Importer les contenus : ' + tab.pagination.start + '/' + tab.count_entities.to_import"
                  severity="success"
                  @click="ImportContentNotExit(tab)"
                  :disabled="tab.pagination.run"
                />
              </div>
              <div class="col">
                <Button label="Gestion de l'import des contenus" severity="info" @click="ManageImportContent(tab)" />
              </div>
            </div>
            <Dialog
              v-model:visible="tab.show_json"
              maximizable
              modal
              :header="'Gerer l\'import de l\'entite : ' + tab.title"
              :style="{ width: '90vw' }"
              :breakpoints="{ '1199px': '90vw', '575px': '95vw' }"
            >
              <h5 class="fw-light my-0">Contenu sur Drupal 7</h5>
              <DataTable :value="tab.entities" tableStyle="min-width: 50rem">
                <Column field="id" header="#id"></Column>
                <Column field="name" header="Titre"></Column>
                <Column field="status" header="Status">
                  <template #body="slotProps">
                    <Tag :value="slotProps.data.status ? 'Active' : 'Disabled'" :severity="slotProps.data.status ? 'success' : 'warn'" />
                  </template>
                </Column>
                <Column field="action" header="#action">
                  <template #body="slotProps">
                    <Button icon="pi pi-upload" class="fw-bold" rounded raised @click="importOneContent(slotProps.data, tab)" :disabled="slotProps.data.run" />
                  </template>
                </Column>
              </DataTable>
            </Dialog>
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
import InputNumber from "primevue/inputnumber";
import Checkbox from "primevue/checkbox";
import Dialog from "primevue/dialog";
import Toast from "primevue/toast";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
// import ColumnGroup from 'primevue/columngroup';   // optional
// import Row from 'primevue/row';                   // optional
import { useToast } from "primevue/usetoast";

const props = defineProps(["bundles", "base_table", "bundle_key", "entity_type_id", "entity_key_id", "entity_key_label"]);
/**
 * Ce gap permet de ne pas ecraser les paragraphes.
 */
const gap_paragraph = 100;
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

const importOneContent = (data, tab) => {
  //console.log("importOneContent data : ", data, "\n id : ", data.id, props);
  importEntity(tab.id, data.id).then((entity) => {
    //import des sous entites multifields.
    if (props.entity_type_id == "multifield") {
      console.log("data :: ", data, "\n entity : ", entity, "\n tab : ", tab);
      if (entity[tab.id]["und"].length > 0)
        for (const id in entity[tab.id]["und"]) {
          const sub_entity = { parent_type: "node", parent_field_name: tab.id, type: tab.id, ...entity[tab.id]["und"][id] };
          // Ajout du gap.
          sub_entity.id = parseInt(sub_entity.id) + gap_paragraph;
          console.log("sub_entity :: ", sub_entity);
          buildAndCreateEntity(sub_entity, tab)
            .then(() => {
              toast.add({ severity: "success", summary: "Le contenu a été importer ", detail: "Contenu creer ou mise à jour :", life: 8000 });
            })
            .catch(() => {
              toast.add({ severity: "error", summary: "Error lors de la creation du contenu", detail: "Errors", life: 8000 });
            });
        }
      //toast.add({ severity: "warn", summary: "L'import doit se faire directement par le contenu parent", detail: "Alert", life: 8000 });
    } else
      buildAndCreateEntity(entity, tab)
        .then(() => {
          toast.add({ severity: "success", summary: "Le contenu a été importer ", detail: "Contenu creer ou mise à jour :", life: 8000 });
        })
        .catch(() => {
          toast.add({ severity: "error", summary: "Error lors de la creation du contenu", detail: "Errors", life: 8000 });
        });
  });
};

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
        pagination: { start: 0, length: 2, run: false, continue: false },
        count_entities: {
          to_import: 0,
          imported: 0,
        },
        entities: [],
      });
    }
  }
};

/**
 * Permet de verifier la configuration.
 *
 */
const CheckConfig = (tab) => {
  // reset datas :
  tab.fields.d10 = [];
  tab.fields.d7 = [];
  tab.fields.errors = [];
  tab.fields.extra_fields = [];
  tab.messagesConfig = [];
  tab.messagesFields = [];

  const url = config.getCustomDomain() + "/admin/migration-hbk-auto/manage-config";
  console.log("url : ", url);
  config
    .get("/migrateexport/migrate-export-entities/" + props.entity_type_id + "/" + tab.id)
    .then((result) => {
      if (result.data) {
        tab.count_entities.to_import = result.data[tab.id].count_entities;
        tab.messagesConfig.push({
          content: "Contenu à importer : " + result.data[tab.id].count_entities,
          id: "d7_import",
          status: true,
          value: result.data[tab.id].count_entities,
        });
        let config_id = props.entity_type_id + "." + props.bundle_key + "." + tab.id;
        // if ("taxonomy_term" == props.entity_type_id) {
        //   config_id = "taxonomy.vocabulary." + tab.id;
        // }
        if ("multifield" == props.entity_type_id) {
          config_id = "paragraph.type." + tab.id;
        } else if ("bean" == props.entity_type_id) {
          config_id = "block_content.type." + tab.id;
        }
        const datas = { config_id: config_id, datas: result.data[tab.id] ? result.data[tab.id] : result.data };
        config.post(url, datas).then((resultD10) => {
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
        toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 8000 });
      }
    })
    .catch((er) => {
      console.log("er : ", er);
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 8000 });
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
    tab.fields.d10.push({ label: field.field_config.label, id: field.id, is_manuel_creation: fieldsD7[i] ? false : true, field_config: field.field_config });
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
    tab.fields.d10;
    tab.fields.extra_fields.push({ label: field.label, id: d, content: field });
  }
};

const CreateFieldsNotExist = (tab) => {
  tab.messagesFields = [];
  let bundle_key = props.bundle_key;
  let entity_type_id = props.entity_type_id;
  if (props.entity_type_id == "taxonomy_term") bundle_key = "vid";
  else if (props.entity_type_id == "multifield") {
    bundle_key = "type";
    entity_type_id = "paragraph";
  } else if (props.entity_type_id == "bean") {
    //bundle_key = "type";
    entity_type_id = "block_content";
  }
  config
    .post(config.getCustomDomain() + "/admin/migration-hbk-auto/generate-fields", {
      fields: tab.fields.errors,
      entity_type: entity_type_id,
      bundle_key: bundle_key,
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
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 8000 });
    });
};
const ReCreateAllFields = (tab) => {
  //
  console.log("tab : ", tab);
};
const ImportContentNotExit = (tab) => {
  const start = tab.pagination.start;
  const length = tab.pagination.length;
  tab.pagination.run = true;
  // Cette approche est centre d'avantage sur les nodes.
  config
    .get("/migrateexport/export-import-entities/load-entities/" + props.entity_type_id + "/" + tab.id + "/" + start + "/" + length)
    .then((reult) => {
      if (reult.data) {
        const promises = [];
        for (var id in reult.data) {
          promises.push(buildAndCreateEntity(reult.data[id], tab));
        }
        Promise.all(promises)
          .then(() => {
            tab.pagination.start = start + length;
            console.log("all entities creates");
            toast.add({ severity: "success", summary: "Tous les contenus ont été crees ", detail: "Contenu creer ou mise à jour :", life: 8000 });
            tab.pagination.run = false;
            if (tab.pagination.continue)
              setTimeout(() => {
                ImportContentNotExit(tab);
              }, 1500);
          })
          .catch((er) => {
            // tab.pagination.start = start + length;
            console.log("Promise.all er :: ", er);
            tab.pagination.run = false;
            tab.pagination.continue = false;
            console.log("all entities creates, with erros");
          });
      } else {
        toast.add({ severity: "success", summary: "Tous les contenus ont deja été importés ", detail: "Contenu deja importés", life: 8000 });
        tab.pagination.run = false;
        tab.pagination.continue = false;
      }
    })
    .catch((er) => {
      console.log("er : ", er);
      toast.add({ severity: "error", summary: "Une erreur s'est produite", detail: "Message Content", life: 8000 });
    });
};
/**
 * On verifie si le paragraphe existe et on retourne l'id.
 * Sinon erreur
 * @param multifieldDatas
 * @param field_config
 */
const buildMultiFieldDatas = (multifieldDatas) => {
  return new Promise((resolv, reject) => {
    const CheckParagrphExist = (values) => {
      return new Promise((url_resolv, url_reject) => {
        const paragraph_id = parseInt(values.id) + gap_paragraph;
        const url = config.getCustomDomain() + "/apivuejs/canonical-entity/paragraph/" + paragraph_id;
        config
          .get(url)
          .then((result) => {
            if (result.data) {
              url_resolv(paragraph_id);
            } else {
              url_reject("Impossible de recuperer les données du paragraphe");
            }
          })
          .catch(() => {
            url_reject("Impossible de recuperer les données du paragraphe");
          });
      });
    };
    const traiterTableau = async (Datas) => {
      const newValues = [];
      if (Datas.und)
        for (const values of Datas.und) {
          if (values.id) {
            await CheckParagrphExist(values)
              .then((id) => {
                newValues.push(id);
              })
              .catch((er) => {
                reject(er);
              });
          } else {
            reject("Impossible de recuperer les données du paragraphe");
          }
        }
      return newValues;
    };
    resolv(traiterTableau(multifieldDatas));
  });
};

/**
 * Return true if object is empty.
 * @param obj
 */
const isEmptyObject = (obj) => {
  return Object.keys(obj).length === 0;
};

/**
 * Il faudra eviter d'envoyer une masse importante de données.
 *
 * Construction des champs, lors de cette construction on peut avoir besoin de creer d'autres données nessaire et recupere l'id final.
 * On ferra cela dans une boucle personnalisé afin de controller le processus.
 * @param tab
 *
 */
const buildAndCreateEntity = async (entity, tab) => {
  return new Promise((content_create, error_create_content) => {
    buildBaseInfoForEntity(entity, tab)
      .then((result) => {
        const baseInfo = result;
        const entity_title = baseInfo.entity_title;
        const values = baseInfo.base_entity;
        /**
         * Recupere les données par champs.
         * @param values
         * @param field_config
         */
        const retriveDataInField = (values, field_config, fieldD7) => {
          return new Promise((retrive_resolv, retrive_reject) => {
            const datas = [];
            if (values.und) {
              // On importe les images si ele n'existe pas.
              if (field_config.field_type == "image") {
                config
                  .post(config.getCustomDomain() + "/admin/migration-hbk-auto/import-files", { files: values.und, base_url: "http://you-v7.kksa/sites/you.fr/files" })
                  .then((result) => {
                    const files = result.data;
                    values.und.forEach((item) => {
                      if (files[item.fid]) {
                        datas.push({
                          target_id: item.fid,
                          alt: item.alt ? item.alt : entity_title,
                          title: item.title,
                          width: item.width,
                          height: item.height,
                        });
                      }
                    });
                    retrive_resolv(datas);
                  })
                  .catch(() => {
                    retrive_reject("Impossible de recuperer les images");
                  });
              }
              // On cree les entites de reference s'ils n'existent pas.
              else if (field_config.field_type == "entity_reference") {
                // console.log("Entity_reference : ", field_config, "\n Values : ", values);
                // cas des tags
                if (field_config.settings.handler == "default:taxonomy_term") {
                  config
                    .post(config.getCustomDomain() + "/admin/migration-hbk-auto/import-terms", {
                      terms: values.und,
                      vocabularies: field_config.settings.handler_settings.target_bundles,
                    })
                    .then((result) => {
                      const terms = result.data;
                      values.und.forEach((term) => {
                        if (terms[term.tid])
                          datas.push({
                            target_id: term.tid,
                          });
                        else {
                          // On desactive cette partie pour le moment.
                          // retrive_reject(
                          //   "Le terme taxo : '" + term.tid + "' du vocabulaire '" + JSON.stringify(field_config.settings.handler_settings.target_bundles) + "' n'existe pas"
                          // );
                          console.log(
                            "error : ",
                            "Le terme taxo : '" + term.tid + "' du vocabulaire '" + JSON.stringify(field_config.settings.handler_settings.target_bundles) + "' n'existe pas"
                          );
                        }
                      });
                      retrive_resolv(datas);
                    })
                    .catch(() => {
                      retrive_reject("Une erreur s'est produite lors de la verification des termes");
                    });
                } else if (field_config.settings.handler == "views" || field_config.settings.handler == "default:node") {
                  // pour l'instant, on n'a pas trouver comment verifier les données pour ce cas.
                  values.und.forEach((entity_reference) => {
                    datas.push({
                      target_id: entity_reference.target_id,
                    });
                  });
                  retrive_resolv(datas);
                } else retrive_reject("L'entite de reference n'est pas encore traiter : " + JSON.stringify(field_config));
              }
              // Les paragraphes doivent etre creer, on verifie s'ils existent, et on retourne l'id.
              else if (fieldD7.type_field == "multifield") {
                buildMultiFieldDatas(values)
                  .then((result) => {
                    if (result.length > 0)
                      result.forEach((paragraph_id) => {
                        datas.push({
                          target_id: paragraph_id,
                          target_revision_id: paragraph_id,
                        });
                      });
                    retrive_resolv(datas);
                  })
                  .catch((er) => {
                    retrive_reject(er);
                  });
              } else if (field_config.field_type == "google_map_field") {
                values.und.forEach((item) => {
                  const data = {};
                  if (item.lat) {
                    data.lat = item.lat;
                  }
                  if (item.lon) {
                    data.lon = item.lon;
                    // On definit la valeur par defaut provenant de configuration.
                    if (field_config.default_value && field_config.default_value[0] && field_config.default_value[0].marker_icon) {
                      data.marker_icon = field_config.default_value[0].marker_icon;
                    }
                  }
                  if (item.map_height) {
                    data.height = item.map_height;
                  }
                  if (item.map_width) {
                    data.width = item.map_width;
                  }
                  if (item.name) {
                    data.name = item.name;
                  }
                  if (item.zoom) {
                    data.zoom = item.zoom;
                  }
                  datas.push(data);
                });
                retrive_resolv(datas);
              } else {
                values.und.forEach((item) => {
                  const data = {};
                  if (item.value) data.value = item.value;
                  if (item.target_id) data.target_id = item.target_id;
                  if (item.fid) data.target_id = item.fid;
                  if (item.alt) data.alt = item.alt;
                  if (item.format) data.format = item.format;
                  if (item.summary) data.summary = item.summary;
                  if (item.rgb) {
                    data.color = item.rgb;
                    data.name = "";
                  }
                  // S'il nya pas de valeur on affiche une erreur.
                  if (isEmptyObject(data)) {
                    console.log(field_config);
                    retrive_reject("Le contenu de la valeur est n'est pas traiter : " + JSON.stringify(item));
                  }
                  datas.push(data);
                });
                retrive_resolv(datas);
              }
            } else {
              console.log("Erreur, contenu du champs ", values, "\n Information sur le champs : ", field_config);
              retrive_reject("Impossible de recuperer les données");
            }
          });
        };
        const getInfosAboutD7filed = (fieldName) => {
          let NewItem = false;
          tab.fields.d7.forEach((item) => {
            if (item.id == fieldName) {
              NewItem = item;
            }
          });
          return NewItem;
        };

        /**
         * Construction des champs, lors de cette construction on peut avoir besoin de creer d'autres données nessaire et recupere l'id final.
         * On ferra cela dans une boucle personnalisé afin de controller le processus.
         */
        const loopFields = (id, contents) => {
          return new Promise((resolv, reject) => {
            if (tab.fields.d10[id]) {
              const field = tab.fields.d10[id];
              if (!field.is_manuel_creation && entity[field.field_config.field_name] && entity[field.field_config.field_name].und) {
                const fieldD7 = getInfosAboutD7filed(field.field_config.field_name);
                retriveDataInField(entity[field.field_config.field_name], field.field_config, fieldD7)
                  .then((datas) => {
                    // if ("field_popup" == field.field_config.field_name) {
                    //   console.log(field.field_config.field_name, " :: ", datas);
                    // }
                    contents[field.field_config.field_name] = datas;
                    id++;
                    resolv(loopFields(id, contents));
                  })
                  .catch((er) => {
                    reject(er);
                  });
              } else {
                id++;
                resolv(loopFields(id, contents));
              }
            } else {
              resolv(contents);
            }
          });
        };
        loopFields(0, {})
          .then((contents) => {
            var entity_type_id = props.entity_type_id;
            if (props.entity_type_id == "multifield") entity_type_id = "paragraph";
            config
              .post(config.getCustomDomain() + "/apivuejs/save-entity/" + entity_type_id, { ...values, ...contents })
              .then((result) => {
                console.log("result : ", result);
                content_create(result);
              })
              .catch((er) => {
                toast.add({ severity: "error", summary: "Erreur de sauvegarde de l'entite", detail: er, life: 8000 });
                error_create_content(er);
              });
          })
          .catch((er) => {
            console.log("loopFields er :: ", er);
            toast.add({ severity: "error", summary: "Erreur de traitement de l'entité", detail: er, life: 8000 });
            error_create_content(er);
          });
      })
      .catch((er) => {
        console.log("buildBaseInfoForEntity er :: ", er);
        toast.add({ severity: "error", summary: "Erreur de creation des données de base", detail: er, life: 8000 });
        error_create_content(er);
      });
    //
  });
};

const buildBaseInfoForEntity = (entity, tab) => {
  return new Promise((resolv, reject) => {
    const entity_title = entity[props.entity_key_label];
    let values = { [props.entity_key_label]: entity_title, [props.entity_key_id]: entity[props.entity_key_id] };
    // Certains entity ont un champs metafields, il faut le remplir.
    /**
     * Les matatags sont deja importé, on verra cela plus tard.
     */
    if (tab.fields && tab.fields.d10) {
      tab.fields.d10.forEach((field) => {
        if (field.field_config && field.field_config.field_type == "metatag" && entity.metatags) {
          for (const i in entity.metatags) {
            const metatagValues = {};
            for (const j in entity.metatags[i]) {
              const meta = entity.metatags[i][j];
              if (j == "title") {
                metatagValues["title"] = meta.value;
                metatagValues["og_title"] = meta.value;
              } else if (j == "description") {
                metatagValues["description"] = meta.value;
                metatagValues["og_description"] = meta.value;
              } else if (j == "robots") {
                //
              } else {
                reject("Le type de metafield n'est pas encore definit", meta);
              }
            }
            if (!isEmptyObject(metatagValues)) {
              values[field.field_config.field_name] = [{ value: metatagValues }];
            }
          }
        }
      });
    }
    switch (props.entity_type_id) {
      case "node":
        values = {
          ...values,
          uid: entity.uid,
          type: entity.type,
          created: entity.created,
          changed: entity.changed,
          language: entity.language,
          status: entity.status,
        };
        break;
      case "multifield":
        values = {
          parent_type: entity.parent_type,
          parent_field_name: entity.parent_field_name,
          type: entity.type,
        };
        if (entity.id) {
          values.id = entity.id;
        }

        break;
      case "taxonomy_term":
        values = {
          ...values,
          description: [{ value: entity.description, format: "full_html" }],
          vid: entity.vocabulary_machine_name,
          weight: entity.weight,
          parent: [{ target_id: entity.parent.und[0].target_id }],
        };
        break;
      default:
        break;
    }
    resolv({ base_entity: values, entity_title: entity_title });
  });
};

/**
 *
 * @param tab
 */
const ManageImportContent = (tab) => {
  const start = tab.pagination.start;
  const length = tab.pagination.length;
  tab.pagination.run = true;
  tab.show_json = true;
  tab.entities = [];
  if (tab.entities.length == 0) {
    importEntities(tab.id, start, length).then((entities) => {
      if (entities) {
        for (const id in entities) {
          const entity = entities[id];
          tab.entities.push({
            id: id,
            name: entity[props.entity_key_label],
            status: parseInt(entity.status),
            run: false,
          });
        }
      }
    });
  }
};

/**
 * Importer les contenus.
 * @param bundle
 * @param start
 * @param length
 */
const importEntities = (bundle, start, length) => {
  return new Promise((resolv, reject) => {
    config
      .get("/migrateexport/export-import-entities/load-entities/" + props.entity_type_id + "/" + bundle + "/" + start + "/" + length)
      .then((result) => {
        if (result.data) {
          resolv(result.data);
        } else resolv(false);
      })
      .catch((er) => {
        reject(er);
      });
  });
};

/**
 *
 * @param bundle
 * @param entity_id
 */
const importEntity = (bundle, entity_id) => {
  return new Promise((resolv, reject) => {
    config
      .get("/migrateexport/export-import-entity/load-entity/" + props.entity_type_id + "/" + bundle + "/" + entity_id)
      .then((result) => {
        if (result.data) {
          resolv(result.data);
        } else resolv(false);
      })
      .catch((er) => {
        reject(er);
      });
  });
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
