(function() {
  "use strict";
  var render = function() {
    var _vm = this;
    var _h = _vm.$createElement;
    var _c = _vm._self._c || _h;
    return _c("div", { staticClass: "k-heading-structure" }, [_c("div", { staticClass: "k-heading-structure-label k-field-label" }, [_c("k-icon", { attrs: { "type": "headline" } }), _c("span", [_vm._v(_vm._s(_vm.label || _vm.$t("heading-structure")))])], 1), _c("k-box", { attrs: { "theme": "" } }, [_c("ol", { staticClass: "k-heading-structure-list" }, _vm._l(_vm.value, function(item, index) {
      return _c("li", { key: index, class: "k-heading-structure-item level-" + item.level + " " + (_vm.itemInvalid(item, index) ? "is-invalid" : ""), style: "z-index: " + (_vm.value.length - index) }, [_c("span", { staticClass: "k-heading-structure-item-level" }, [_vm._v("H" + _vm._s(item.level))]), _c("span", { staticClass: "k-heading-structure-item-text" }, [_vm._v(_vm._s(item.text))])]);
    }), 0)]), _vm.incorrectOrder ? _c("k-box", { staticClass: "k-heading-structure-notice", attrs: { "theme": "negative" } }, [_c("k-icon", { attrs: { "type": "alert" } }), _c("k-text", [_vm._v(_vm._s(_vm.$t("incorrect-heading-order")))])], 1) : _vm._e(), _vm.multipleH1 ? _c("k-box", { staticClass: "k-heading-structure-notice", attrs: { "theme": "negative" } }, [_c("k-icon", { attrs: { "type": "alert" } }), _c("k-text", [_vm._v(_vm._s(_vm.$t("multiple-h1-tags")))])], 1) : _vm._e(), _vm.noH1 ? _c("k-box", { staticClass: "k-heading-structure-notice", attrs: { "theme": "negative" } }, [_c("k-icon", { attrs: { "type": "alert" } }), _c("k-text", [_vm._v(_vm._s(_vm.$t("missing-h1-tag")))])], 1) : _vm._e()], 1);
  };
  var staticRenderFns = [];
  render._withStripped = true;
  var headingStructure_vue_vue_type_style_index_0_lang = "";
  function normalizeComponent(scriptExports, render2, staticRenderFns2, functionalTemplate, injectStyles, scopeId, moduleIdentifier, shadowMode) {
    var options = typeof scriptExports === "function" ? scriptExports.options : scriptExports;
    if (render2) {
      options.render = render2;
      options.staticRenderFns = staticRenderFns2;
      options._compiled = true;
    }
    if (functionalTemplate) {
      options.functional = true;
    }
    if (scopeId) {
      options._scopeId = "data-v-" + scopeId;
    }
    var hook;
    if (moduleIdentifier) {
      hook = function(context) {
        context = context || this.$vnode && this.$vnode.ssrContext || this.parent && this.parent.$vnode && this.parent.$vnode.ssrContext;
        if (!context && typeof __VUE_SSR_CONTEXT__ !== "undefined") {
          context = __VUE_SSR_CONTEXT__;
        }
        if (injectStyles) {
          injectStyles.call(this, context);
        }
        if (context && context._registeredComponents) {
          context._registeredComponents.add(moduleIdentifier);
        }
      };
      options._ssrRegister = hook;
    } else if (injectStyles) {
      hook = shadowMode ? function() {
        injectStyles.call(
          this,
          (options.functional ? this.parent : this).$root.$options.shadowRoot
        );
      } : injectStyles;
    }
    if (hook) {
      if (options.functional) {
        options._injectStyles = hook;
        var originalRender = options.render;
        options.render = function renderWithStyleInjection(h, context) {
          hook.call(context);
          return originalRender(h, context);
        };
      } else {
        var existing = options.beforeCreate;
        options.beforeCreate = existing ? [].concat(existing, hook) : [hook];
      }
    }
    return {
      exports: scriptExports,
      options
    };
  }
  const __vue2_script = {
    data() {
      return {
        label: null,
        value: null
      };
    },
    created() {
      this.handleLoad();
    },
    computed: {
      changes() {
        return this.$store.getters["content/changes"]();
      },
      incorrectOrder() {
        var _a;
        return (_a = this.value) == null ? void 0 : _a.some((item, index) => {
          var _a2, _b;
          return item.level > ((_b = (_a2 = this.value[index - 1]) == null ? void 0 : _a2.level) != null ? _b : 0) + 1;
        });
      },
      multipleH1() {
        var _a;
        return ((_a = this.value) == null ? void 0 : _a.filter((item) => item.level === 1).length) > 1;
      },
      noH1() {
        var _a;
        return ((_a = this.value) == null ? void 0 : _a.filter((item) => item.level === 1).length) === 0;
      }
    },
    methods: {
      async handleLoad(changes) {
        let newChanges = {};
        Object.entries(changes != null ? changes : this.changes).map(([key, value]) => {
          newChanges[key] = encodeURIComponent(JSON.stringify(value));
        });
        const response = await this.$api.get(this.parent + "/sections/" + this.name, newChanges);
        this.value = response.value;
        this.label = response.label;
      },
      itemInvalid(item, index) {
        var _a, _b;
        if (item.level > ((_b = (_a = this.value[index - 1]) == null ? void 0 : _a.level) != null ? _b : 0) + 1)
          return true;
        if (item.level === 1 && this.value[index - 1])
          return true;
        if (item.level === 1 && this.value.filter((item2) => item2.level === 1).length > 1)
          return true;
        return false;
      }
    },
    watch: {
      changes(changes) {
        this.handleLoad(changes);
      }
    }
  };
  const __cssModules = {};
  var __component__ = /* @__PURE__ */ normalizeComponent(
    __vue2_script,
    render,
    staticRenderFns,
    false,
    __vue2_injectStyles,
    null,
    null,
    null
  );
  function __vue2_injectStyles(context) {
    for (let o in __cssModules) {
      this[o] = __cssModules[o];
    }
  }
  __component__.options.__file = "src/sections/heading-structure.vue";
  var headingStructure = /* @__PURE__ */ function() {
    return __component__.exports;
  }();
  var __glob_1_0 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
    __proto__: null,
    "default": headingStructure
  }, Symbol.toStringTag, { value: "Module" }));
  const getComponentName = (path) => path.substring(path.lastIndexOf("/") + 1, path.lastIndexOf(".")).toLowerCase();
  const kirbyup = Object.freeze({
    import(modules) {
      return Object.entries(modules).reduce((accumulator, [path, component]) => {
        accumulator[getComponentName(path)] = component.default;
        return accumulator;
      }, {});
    }
  });
  panel.plugin("tobimori/meta", {
    sections: kirbyup.import({ "./sections/heading-structure.vue": __glob_1_0 })
  });
})();
