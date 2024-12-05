import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import ThemeRender from "@primevue/themes/lara";
import ToastService from "primevue/toastservice";
import "./assets/bootstrap.scss";
const application = createApp(App);

// Ajout de primevue.
application.use(PrimeVue, {
  theme: {
    preset: ThemeRender,
    options: {
      prefix: "prime",
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
// import toast
application.use(ToastService);

// ajout des composants de maniere global
// TodoDeleteButton import "..."
// application.component("TodoDeleteButton", TodoDeleteButton);

// mount App.
application.mount("#migrate-app-build");

// https://vuejs.org/api/#composition-api
