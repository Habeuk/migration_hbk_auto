import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import ThemeRender from "@primevue/themes/lara";

const application = createApp(App);

// Ajout de primevue.
application.use(PrimeVue, {
  theme: {
    preset: ThemeRender,
    options: {
      prefix: "p",
      darkModeSelector: "system",
      cssLayer: false,
    },
  },
});

// Gestion des erreurs :
application.config.errorHandler = (err) => {
  /* gérer l'erreur */
  console.log("Main app error : ", err);
};
// ajout des composants de maniere global
// TodoDeleteButton import "..."
// application.component("TodoDeleteButton", TodoDeleteButton);

// mount App.
application.mount("#migrate-app-build");

// https://vuejs.org/api/#composition-api
