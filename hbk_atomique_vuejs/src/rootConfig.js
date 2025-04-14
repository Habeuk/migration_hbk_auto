import AjaxBasic from "wbuutilities/src/Ajax/basic.js";
// const AjaxBasic = null;
/**
 * Permet de determiner le domaine interne. ( pour des besoins de developpement plusieurs domaines sont disponible )
 */
const getCustomDomain = async (external = false) => {
  if (external) {
    const response = await fetch("/admin/migration-hbk-auto/get-migration-settings");
    if (response.status == 200) {
      const datas = await response.json();
      if (datas.source_site_url) {
        return datas.source_site_url;
      } else {
        throw new Error("Custom domain not found in response");
      }
    } else {
      return window.location.protocol + "//" + window.location.host;
    }
  } else {
    return window.location.protocol + "//" + window.location.host;
  }
};
const url = await getCustomDomain(true);
// configurer pour recuperer les données vers le site drupal7.
const config = {
  ...AjaxBasic,
  baseUrl: url,
  requestDomain: null,
  languageId: "",
  // on ne laisse la valeur par defaut, pour permttre au domaine local de pouvoir se connecter.
  TestDomain: url,
  /**
   * Retoune un entier arleatoire entre [99-999]
   */
  getRandomIntInclusive(min = 99, max = 999) {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min + 1)) + min;
  },
  getCustomDomain(external = false) {
    if (this.requestDomain) {
      this.requestDomain = getCustomDomain(external);
    }
    return this.requestDomain ? this.requestDomain : window.location.protocol + "//" + window.location.host;
  },
};
export default config;
