/**
 * Permet de merger les informations fournit par Drupal 7 et Drupal 10.
 */
class NodesMerge {
  constructor(configV7, configV10) {
    this.configV7 = configV7;
    this.configV10 = configV10;
  }

  buildConfigD10() {
    if (!this.configIsBuilded()) {
      //
    }
  }

  configIsBuilded() {
    return this.configV10 ? true : false;
  }
}
export default NodesMerge;
