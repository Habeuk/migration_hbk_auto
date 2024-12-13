import { AjaxBasic } from "wbuutilities";
/**
 * Permet de determiner le domaine interne. ( pour des besoins de developpement plusieurs domaines sont disponible )
 */
const getDomain = (external = false) => {
  if (external) return "http://you-v7.kksa";
  else {
    return window.location.protocol + "://" + window.location.host;
  }
};
const config = {
  ...AjaxBasic,
  languageId: "",
  // on ne laisse la valeur par defaut, pour permttre au domaine local de pouvoir se connecter.
  TestDomain: getDomain(true),
  /**
   * Retoune un entier arleatoire entre [99-999]
   */
  getRandomIntInclusive(min = 99, max = 999) {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min + 1)) + min;
  },
  getDomain(external = false) {
    return getDomain(external);
  },
};
export default config;
