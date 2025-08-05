// src/App.js
import React, { useEffect, useMemo, useRef } from "react";
import { ThemeProvider, createGlobalStyle } from "styled-components";
import Theme from "modules/theme-config/theme-config";
import "bootstrap/dist/css/bootstrap.min.css";
import Router from "./routes";
import { useSelector, useDispatch } from "react-redux";
import ErrorBoundary from "./utils/ErrorBoundary";
import "./css/globalcss.css";
import { ThemeConf, LinkTrigger, getVahaanConfig } from "modules/Home/home.slice";
import { TypeAccess } from "modules/login/login.slice";
import SecureLS from "secure-ls";
import _ from "lodash";
import {
  useLoginWidget,
  useUnloadBeacon,
  LogoLoader,
  NetworkStatus,
} from "components";
import { hotjar } from "react-hotjar";
import ReactGA from "react-ga4";
import CacheBuster from "react-cache-buster";
import packageJson from "../package.json";
import {
  fetchToken,
  _preventDuplicateTab,
  _setAgentSession,
  getPageName,
  meta,
  isB2B,
  allowedClevertapRoutes,
  initializeCleverTap,
} from "utils";
import DocumentMeta from "react-document-meta";
import { typeRoute } from "modules/type";

// Instantiate SecureLS for caching theme data
const ls = new SecureLS();

/**
 * Helper function to inject a script into the document head.
 * Avoids duplicate insertion if an element with the same id exists.
 *
 * @param {Object} options
 * @param {string} [options.id] - Optional element ID.
 * @param {string} [options.innerHTML] - Inline script content.
 * @param {string} [options.src] - External script URL.
 * @param {boolean} [options.async] - Whether to load asynchronously (default: true).
 * @param {string} [options.crossOrigin] - Optional crossOrigin attribute.
 */
const loadScript = ({ id, innerHTML, src, async = true, crossOrigin }) => {
  if (id && document.getElementById(id)) return; // avoid duplicate insertion
  const script = document.createElement("script");
  if (id) script.id = id;
  if (innerHTML) script.innerHTML = innerHTML;
  if (src) script.src = src;
  script.async = async;
  if (crossOrigin) script.crossOrigin = crossOrigin;
  document.head.appendChild(script);
};

const App = () => {
  const dispatch = useDispatch();
  const {
    theme_conf,
    theme_conf_success,
    temp_data: temp,
    vahaanConfig,
  } = useSelector((state) => state.home);
  const { temp_data } = useSelector((state) => state.proposal);

  // Get enquiry id and token from URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const enquiry_id = urlParams.get("enquiry_id");
  const token = urlParams.get("xutm");


  // Broadcast message for RB broker
  if (import.meta.env.VITE_BROKER === "RB" && window?.ReactNativeWebView) {
    window.ReactNativeWebView.postMessage(window.location.href);
  }

  // Session management: clear localStorage after a set period (30 minutes)
  const hours = 0.5;
  const now = new Date().getTime();
  const setupTime = localStorage.getItem("setupTime");
  if (
    !setupTime &&
    token &&
    !["payment-success", "proposal-page", "quotes"].includes(
      window.location.href
    )
  ) {
    localStorage.setItem("setupTime", now);
  } else if (setupTime && now - setupTime > hours * 60 * 60 * 1000) {
    localStorage.removeItem("setupTime");
  }

  // Unload Beacon for when the page unloads
  useUnloadBeacon({
    url: `${import.meta.env.VITE_API_BASE_URL}/linkDelivery`,
    payload: () => {
      let formdata = new FormData();
      formdata.append("dropout", "dropout");
      formdata.append("user_product_journey_id", enquiry_id);
      return formdata;
    },
  });

  // Journey continue and WebEngage tracking
  const windowState = window.location.href;
  useEffect(() => {
    if (enquiry_id) {
      dispatch(
        LinkTrigger(
          { user_product_journey_id: enquiry_id, dropout: "continue" },
          true
        )
      );
    }
    if (window.webengage) {
      window.webengage.track("Page Viewed", {
        "Page Name": getPageName(windowState),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [windowState]);

  // Load theme configuration and prevent duplicate tabs
  useMemo(() => {
    dispatch(ThemeConf(false, { enquiry_id }));
    if (enquiry_id && (sessionStorage.getItem("lsToken") || fetchToken())) {
      _preventDuplicateTab(enquiry_id);
    }
  }, [enquiry_id]);

  // Store theme data in local storage
  useEffect(() => {
    if (theme_conf?.theme_config && !_.isEmpty(theme_conf.theme_config)) {
      ls.set("themeData", theme_conf.theme_config);
    } else {
      ls.remove("themeData");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme_conf?.theme_config]);

  useEffect(()=>{
    if(_.isEmpty(vahaanConfig)){
      dispatch(getVahaanConfig({enquiry_id}));  
    }
  },[enquiry_id])

  // Type-Access & clearing SSO data
  useEffect(() => {
    dispatch(TypeAccess());
    if (import.meta.env.VITE_BROKER !== "RB") {
      localStorage.removeItem("SSO_user");
      localStorage.removeItem("SSO_user_motor");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  let isB2C =
    (!_.isEmpty(temp) && !isB2B(temp)) ||
    (!_.isEmpty(temp_data) && !isB2B(temp_data));

  // Initialize Clevertap if conditions match
  const isCleverTapInitialized = useRef(false);
  useEffect(() => {
    if (
      !isCleverTapInitialized.current &&
      isB2C &&
      allowedClevertapRoutes() &&
      import.meta.env.VITE_BROKER === "HEROCARE" &&
      !token
    ) {
      initializeCleverTap();
      isCleverTapInitialized.current = true;
    }
  }, [JSON.stringify(temp), JSON.stringify(temp_data), isB2C]);

  // RenewBuy SSO trigger if needed
  const SSO = () => {
    useLoginWidget();
  };
  if (import.meta.env.VITE_BROKER === "RB") SSO();

  // Adjust meta viewport for iOS zoom issues
  useEffect(() => {
    const el = document.querySelector("meta[name=viewport]");
    if (el) {
      let content = el.getAttribute("content");
      const re = /maximum\-scale=[0-9\.]+/g;
      if (re.test(content)) {
        content = content.replace(re, "maximum-scale=1.0");
      } else {
        content = [content, "maximum-scale=1.0"].join(", ");
      }
      el.setAttribute("content", content);
    }
  }, []);

  /*--- hotjar ---*/
  useEffect(() => {
    if (
      window.location.href.includes("/car/") &&
      !hotjar.initialized() &&
      import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_PROD === "YES"
    ) {
      hotjar.initialize("3110385", 6);
    }
    if (
      window.location.href.includes("/bike/") &&
      !hotjar.initialized() &&
      import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_PROD === "YES"
    ) {
      hotjar.initialize("3110405", 6);
    }
  }, [window.location.href]);

  // Payment-success hotjar initialization
  useEffect(() => {
    if (
      (window.location.href.includes("/payment-success/") ||
        window.location.href.includes("/payment-failure/")) &&
      !hotjar.initialized() &&
      import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_PROD === "YES"
    ) {
      if (temp_data?.productSubTypeCode === "CAR") {
        hotjar.initialize("3110385", 6);
      }
      if (temp_data?.productSubTypeCode === "BIKE") {
        hotjar.initialize("3110405", 6);
      }
    }
  }, [temp_data]);
  /*--- end hotjar ---*/

  /*--- Google Analytics ---*/
  if (import.meta.env.VITE_BROKER === "BAJAJ") {
    ReactGA.initialize("UA-45018221-13");
  }
  if (import.meta.env.VITE_BROKER === "TMIBASL") {
    ReactGA.initialize("G-6ENFDWFF7C");
  }
  if (import.meta.env.VITE_BROKER === "HEROCARE") {
    ReactGA.initialize("G-1BJP2E9Q3C");
  }
  /*--- end Google Analytics ---*/

  /*--- Set Agent ---*/
  const _fetchAgent = (data) =>
    data?.agentDetails?.filter((i) => ["P", "E"].includes(i?.sellerType));
  const agent = _fetchAgent(
    !_.isEmpty(temp) ? temp : !_.isEmpty(temp_data) ? temp_data : []
  );
  useMemo(() => {
    if (!_.isEmpty(agent)) {
      _setAgentSession(
        agent,
        enquiry_id,
        theme_conf?.broker_config?.pc_redirection
      );
    }
  }, [agent]);
  /*--- end Set Agent ---*/

  // Meta data handling
  const dynamicMeta =
    typeRoute &&
    !_.isEmpty(theme_conf?.broker_config?.broker_asset?.title_description)
      ? theme_conf?.broker_config?.broker_asset?.title_description?.[
          typeRoute()
        ]
      : meta(typeRoute, theme_conf);

  // --- Dynamic Script Injection ---
  useEffect(() => {
    // Google Tag Manager (GTM) for BAJAJ
    if (import.meta.env.VITE_BROKER === "BAJAJ") {
      if (import.meta.env.VITE_BASENAME === "general-insurance") {
        loadScript({
          id: "gtm-script-1",
          innerHTML: `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-MKCGTGN6');`,
        });
      }
      // Always load secondary GTM script for BAJAJ
      loadScript({
        id: "gtm-script-2",
        innerHTML: `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-N2RFGG9');`,
      });
      // IBM Instana (for PROD and general-insurance)
      if (
        import.meta.env.VITE_PROD === "YES" &&
        import.meta.env.VITE_BASENAME === "general-insurance"
      ) {
        loadScript({
          id: "instana-init",
          innerHTML: `(function(s,t,a,n){s[t]||(s[t]=a,n=s[a]=function(){n.q.push(arguments);},n.q=[],n.v=2,n.l=1*new Date());})(window,"InstanaEumObject","ineum");
                      ineum("reportingUrl", "https://eum-red-saas.instana.io");
                      ineum("key", "-PAMcKhXTmqwM9Rc8iyAuA");
                      ineum("trackSessions");`,
        });
        loadScript({
          id: "instana-lib",
          src: "https://eum.instana.io/eum.min.js",
          crossOrigin: "anonymous",
        });
      }
    }
    // United India SDK for brokers other than BAJAJ
    if (import.meta.env.VITE_BROKER !== "BAJAJ") {
      loadScript({
        id: "united-india-sdk",
        src: "https://hv-camera-web-sg.s3-ap-southeast-1.amazonaws.com/hyperverge-web-sdk@6.1.1/src/sdk.min.js",
      });
    }
    // Google Analytics
    if (import.meta.env.VITE_GTAG !== "NA") {
      loadScript({
        id: "ga-ua",
        src: "https://www.googletagmanager.com/gtag/js?id=UA-57859131-3",
      });
      loadScript({
        id: "ga-ua-init",
        innerHTML: `
          window.dataLayer = window.dataLayer || [];
          function gtag(){ dataLayer.push(arguments); }
          gtag('js', new Date());
          gtag('config', 'UA-57859131-3');
        `,
      });
      loadScript({
        id: "ga-ga4",
        src: "https://www.googletagmanager.com/gtag/js?id=G-CTWKWSPJQ1",
      });
      loadScript({
        id: "ga-ga4-init",
        innerHTML: `
          window.dataLayer = window.dataLayer || [];
          function gtag(){ dataLayer.push(arguments); }
          gtag('js', new Date());
          gtag('config', 'G-CTWKWSPJQ1');
        `,
      });
    }
  }, []);
  // --- End Dynamic Script Injection ---

  // Render the application
  return (
    <DocumentMeta {...dynamicMeta}>
      <ErrorBoundary>
        {/* <CacheBuster
          currentVersion={packageJson?.version}
          isEnabled={import.meta.env.VITE_PROD === "YES"}
          isVerboseMode={false}
          loadingComponent={<LogoLoader />}
          metaFileDirectory={"."}
        > */}
          <ThemeProvider
            theme={
              !_.isEmpty(theme_conf?.theme_config)
                ? theme_conf.theme_config
                : Theme
            }
          >
            <GlobalStyle broker={import.meta.env?.VITE_BROKER === "ABIBL"} />
            {!theme_conf_success ? (
              <LogoLoader />
            ) : (
              <Router BlockLayout={!theme_conf?.isIpBlocked} />
            )}
            <NetworkStatus />
          </ThemeProvider>
        {/* </CacheBuster> */}
      </ErrorBoundary>
    </DocumentMeta>
  );
};

export default App;

// Global styles using styled-components
export const GlobalStyle = createGlobalStyle`
  body {
    font-family: ${({ theme }) =>
      theme?.regularFont?.fontFamily || "basier_squareregular"};
    .swal-button {
      background-color: ${({ broker }) => (broker ? "#c7222a !important" : "")};
    }
    .swal-button--cancel {
      color: #555;
      background-color: #efefef !important;
    }
  }
  .swal-text {
    font-family: ${({ theme }) =>
      theme?.regularFont?.fontFamily || "basier_squareregular"};
  }
  .backBtn button {
    top: ${({ theme }) => theme?.BackButton?.backButtonTop || "136px"};
    font-family: ${({ theme }) =>
      theme?.regularFont?.fontFamily || "basier_squareregular"};
    @media (max-width: 767px) {
      top: ${({ theme }) => theme?.BackButton?.backButtonTopMobile || "112px"};
    }
  }
`;
