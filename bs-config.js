module.exports = {
  proxy: "http://nomedoseusite.local", // URL do LocalWP
  files: [
    "assets/**/*.css",
    "assets/**/*.js",
    "**/*.php"
  ],
  injectChanges: true,
  open: false
};
