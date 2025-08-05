import React from "react";
import logo from "utils/img/logo.svg";
import ace from "utils/img/ace.png";
import fyntune from "utils/img/fyntune.png";
import paytm from "utils/img/paytm.png";
import abibl from "utils/img/abibl.jpg";
import gc from "utils/img/gc.png";
import sriyah from "utils/img/sriyah.jpg";
import rb from "utils/img/rb.png";
import bajaj from "utils/img/BajajNew.png";
import tata from "utils/img/tata.gif";
import uib from "utils/img/uib.png";
import sridhar from "utils/img/sridhar.png";
import insuringall from "utils/img/insuringall.jpeg";
import policyera from "utils/img/policy-era.png";
import kmd from "utils/img/kmd.png";
import hero_care from "utils/img/hero_care.png";
import karoinsure from "utils/img/karoinsure.png";
import instantbeema from "utils/img/instant-beema.svg";
import vcare from "utils/img/vcare.jpeg";
import womingo from "utils/img/womingo.png";
import oneclick from "utils/img/1clickpolicy.png";
import _ from 'lodash';

// logo funtions
export const LogoFn = () => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return logo;
    case "FYNTUNE":
      return fyntune;
    case "POLICYERA":
      return policyera;
    case "ABIBL":
      return abibl;
    case "GRAM":
      return gc;
    case "ACE":
      return ace;
    case "SRIYAH":
      return sriyah;
    case "RB":
      return rb;
    case "BAJAJ":
      return bajaj;
    case "TATA":
      return tata;
    case "SPA":
      return insuringall;
    case "UIB":
      return uib;
    case "SRIDHAR":
      return sridhar;
    case "KMD":
      return kmd;
    case "HEROCARE":
      return hero_care;
    case "PAYTM":
      return paytm;
    case "KAROINSURE":
      return karoinsure;
    case "INSTANTBEEMA":
      return instantbeema;
    case "VCARE":
      return vcare;
    case "WOMINGO":
      return womingo;
      case "ONECLICK":
        return oneclick
    default:
      break;
  }
};

// Broker Name
export const BrokerName = () => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "Ola Financial Services Private Limited";
    case "PAYTM":
      return "Paytm Insurance pvt. ltd.";
    case "ABIBL":
      return "Aditya Birla Insurance Broker Limited";
    case "GRAM":
      return "GramCover Insurance Brokers Private Limited";
    case "ACE":
      return "ACE Insurance Broker Pvt. Ltd.";
    case "SRIYAH":
      return "Sriyah Insurance Brokers Pvt. Ltd";
    case "RB":
      return "D2C Insurance Broking Pvt. Ltd.";
    case "SPA":
      return "SPA Insurance Broking Services Ltd.";
    case "BAJAJ":
      return "Bajaj Capital Insurance Broking Limited";
    case "UIB":
      return "UIB Insurance Brokers (India) Pvt. Ltd.";
    case "SRIDHAR":
      return "Sridhar Insurance Brokers (P) Ltd.";
    case "POLICYERA":
      return "Policy Era Insurance Broking LLP.";
    case "TATA":
      return "Tata Motors Insurance Broking And Advisory Services Limited.";
    case "HEROCARE":
      return "Hero Insurance Broking India Private Limited";
    case "KAROINSURE":
      return "WHITEHORSE INSURANCE BROKERS PRIVATE LIMITED";
    case "VCARE":
        return "VCARE INSURANCE BROKERS PRIVATE LIMITED";
    case "WOMINGO":
        return "Antworks Insurance Broking & Risk Consulting Pvt. Ltd";
    case "ONECLICK":
        return "Swastika Insurance Broking Services Private Ltd";
    default:
      return "Ola Financial Services Private Limited";
  }
};
//  Broker Category
export const BrokerCategory = (broker) => {
  switch (broker || import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "Corporate Agent";
    case "FYNTUNE":
      return "Composite Broker";
    case "ABIBL":
      return "Composite Broker";
    case "GRAM":
      return "Composite Broker";
    case "ACE":
      return "Composite Broker";
    case "SRIYAH":
      return "Direct Broker";
    case "RB":
      return "Direct Broker (Life & General)";
    case "SPA":
      return "Direct Broker";
    case "BAJAJ":
      return "Direct Broker";
    case "UIB":
      return "Composite Broker";
    case "SRIDHAR":
      return "Direct Broker";
    case "POLICYERA":
      return "Direct Broker";
    case "TATA":
      return "Composite Broker";
    case "HEROCARE":
      return "Composite Broker";
    case "PAYTM":
      return "Direct Broker";
    case "KAROINSURE":
      return "Direct (Life & General)";
    case "VCARE":
        return "Direct (Life & General)";
    case "WOMINGO":
        return "Direct Broker";
    case "ONECLICK":
        return "Direct Broker";
    default:
      return "Composite Broker";
  }
};

// broker email  funtion
export const brokerEmailFunction = (brokerValue) => {
  switch (brokerValue || import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "insurance@olacabs.com";
    case "FYNTUNE":
      return "help@fyntune.com";
    case "ABIBL":
      return "clientfeedback.abibl@adityabirlacapital.com";
    case "GRAM":
      return "info@gramcover.com";
    case "ACE":
      return "operations@aceinsurance.com";
    case "SRIYAH":
      return "care@nammacover.com";
    case "RB":
      return "customersupport@renewbuy.com";
    case "SPA":
      return "care@insuringall.com";
    case "BAJAJ":
      return "care@bajajacapital.com";
    case "UIB":
      return "services@uibindia.com";
    case "SRIDHAR":
      return "motor@sibinsure.com";
    case "POLICYERA":
      return "support@policyera.com";
    case "TATA":
      return "support@tmibasl.com";
    case "HEROCARE":
      return "support.herocare@heroinsurance.com";
    case "PAYTM":
      return "care@paytminsurance.co.in";
    case "VCARE":
      return "po@vcareinsurance.in";
    case "WOMINGO":
      return "support@womingo.com";
    case "ONECLICK":
      return "support@1clickpolicy.com";
    default:
      break;
  }
};
// broker contact number funtion
export const ContactFn = (brokerValue) => {
  switch (brokerValue || import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "08037101822";
    case "FYNTUNE":
      return "1800120000065";
    case "ABIBL":
      return "18002707000";
    case "GRAM":
      return "+91 9311672463";
    case "ACE":
      return "+918750881122";
    case "SRIYAH":
      return "+1800 203 0504";
    case "RB":
      return "18004197852";
    case "SPA":
      return "+91-11-45675555";
    case "BAJAJ":
      return "1800 212 123123";
    case "UIB":
      return "+91 79820 39210";
    case "SRIDHAR":
      return "1800-102-6099";
    case "POLICYERA":
      return "7039839239";
    case "TATA":
      return "18002090060";
    case "HEROCARE":
      return "911140578489";
    case "PAYTM":
      return "+918826390016";
    case "VCARE":
        return "+919950954355";
    case "WOMINGO":
        return "8860-6060-90";    
    default:
      return "18002669639";
    case "ONECLICK":
    return "+91-8055875587";
  }
};
// broker contact number funtion
export const cinNO = (brokerValue) => {
  switch (brokerValue || import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "U67200MH2003PTC141621";
    case "FYNTUNE":
      return "U67200MH2003PTC141621";
    case "ABIBL":
      return "U67200MH2003PTC141621";
    case "GRAM":
      return "U66000DL2016PTC292037";
    case "ACE":
      return "U74999DL2001PTC110729";
    case "SRIYAH":
      return "U66010KA2003PTC031462";
    case "RB":
      return "U66030DL2013PTC249265";
    case "SPA":
      return "U67120MH1995PLC088462";
    case "BAJAJ":
      return "U67200DL2002PLC117625";
    case "UIB":
      return "U66030MH2009PTC195794";
    case "SRIDHAR":
      return "U67120CH2002PTC025491";
    case "POLICYERA":
      return "AAX-7485";
    case "TATA":
      return "U50300MH1997PLC149349";
    case "HEROCARE":
      return "U66010DL2007PTC165059";
    case "PAYTM":
      return "U66000DL2019PTC355671";
    case "VCARE":
        return "U66010RJ2022PTC085222";
    case "WOMINGO":
        return "U67200HR2012PTC046705";
    case "ONECLICK":
        return "U67345MH2C123456";
    default:
      return "";
  }
};
// IRDIA number
export const getIRDAI = (brokerValue) => {
  switch (brokerValue || import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "CA0682";
    case "FYNTUNE":
      return "CA0682";
    case "ABIBL":
      return "CA0682";
    case "GRAM":
      return "CB 691/17";
    case "ACE":
      return "CB/246";
    case "SRIYAH":
      return "203";
    case "RB":
      return "DB 571/14";
    case "SPA":
      return "DB053/03";
    case "BAJAJ":
      return "CB 042/02";
    case "UIB":
      return "410";
    case "SRIDHAR":
      return "212";
    case "POLICYERA":
      return "DB 897/2021";
    case "TATA":
      return "375";
    case "HEROCARE":
      return "649";
    case "PAYTM":
      return "700";
    case "VCARE":
      return "987";
    case "WOMINGO":
      return "DB 522/12";
    case "ONECLICK":
        return "713";  
    default:
      break;
  }
};
// cv logo
export const getLogoCvType = (productSubTypeId) => {
  switch (productSubTypeId) {
    case 5:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/auto.png`;
    case 6:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/taxi-car1.png`;
    case 9:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/trck.png`;
    case 13:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/dumper2.png`;
    case 14:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/pickup.png`;
    case 15:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tractor.png`;
    case 16:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tanker.png`;
    default:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/auto-car.png`;
  }
};
// broker logo url funtion
export const getBrokerLogoUrl = () => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/ola.png`;
    case "FYNTUNE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/fyntune.png`;
    case "POLICYERA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/policy-era.png`;
    case "ABIBL":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/abiblPdf.jpeg`;
    case "GRAM":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/gc.png`;
    case "ACE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/ace.png`;
    case "SRIYAH":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/sriyah.png`;
    case "RB":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/rb.png`;
    case "SPA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/insuringall.jpeg`;
    case "BAJAJ":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/bajajPdfLogo.png`;
    case "UIB":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/uib.png`;
    case "SRIDHAR":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/sridhar.png`;
    case "TATA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tata.gif`;
    case "HEROCARE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/hero_care.png`;
    case "PAYTM":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/paytm.svg`;
    case "KMD":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/kmd.png`;
    case "INSTANTBEEMA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/instant-beema.svg`;
    case "KAROINSURE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/karoinsure.png`;
      case "VCARE":
        return `${window.location.origin}${
          import.meta.env.VITE_BASENAME !== "NA"
            ? `/${import.meta.env.VITE_BASENAME}`
            : ""
        }/assets/images/vehicle/vcare.jpeg`;
      case "WOMINGO":
        return `${window.location.origin}${
          import.meta.env.VITE_BASENAME !== "NA"
            ? `/${import.meta.env.VITE_BASENAME}`
            : ""
        }/assets/images/vehicle/womingo.png`; 
        case "ONECLICK":
        return `${window.location.origin}${
          import.meta.env.VITE_BASENAME !== "NA"
            ? `/${import.meta.env.VITE_BASENAME}`
            : ""
        }/assets/images/vehicle/1clickpolicy.png`;
    default:
      break;
  }
};

// email and phone number with text
export const ContentFn = (brokerValue, theme_conf) => {
  if (!_.isEmpty(theme_conf)) {
    return (
      <>
        In case of any challenges, please contact us at
        <b> {theme_conf?.broker_config?.email}</b> or call us at our number
        <b> {theme_conf?.broker_config?.phone}</b>
      </>
    );
  } else {
    switch (brokerValue || import.meta.env?.VITE_BROKER) {
      case "OLA":
        return (
          <>
            In case of any challenges, please contact us at
            <b> insurance@olacabs.com</b> or call us at our number
            <b> 7829-41-1222</b>
          </>
        );
      case "FYNTUNE":
        return (
          <>
            In case of any challenges, please contact us at
            <b> help@fyntune.com</b> or call us at our number
            <b> 9711615784</b>
          </>
        );
      case "ABIBL":
        return (
          <>
            In case of any challenges, please contact us at
            <b> Support@abibl.com</b> or call us at our number
            <b> 1800 270 7000</b>
          </>
        );
      case "GRAM":
        return (
          <>
            In case of any challenges, please contact us at
            <b> info@gramcover.com</b> or call us at our number
            <b> +91 9311672463</b>
          </>
        );
      case "ACE":
        return (
          <>
            In case of any challenges, please contact us at
            <b> operations@aceinsurance.com</b> or call us at our number
            <b> +918750881122</b>
          </>
        );
      case "SRIYAH":
        return (
          <>
            In case of any challenges, please contact us at
            <b> care@nammacover.com</b> or call us at our number
            <b> 1800 203 0504</b>
          </>
        );
      case "RB":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> customersupport@renewbuy.com</b> or call us at our number
            <b> 18004197852</b>
          </>
        );
      case "SPA":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> care@insuringall.com</b> or call us at our number
            <b> +91-11-45675555</b>
          </>
        );
      case "BAJAJ":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> care@bajajacapital.com</b> or call us at our number
            <b> 1800 212 123123</b>
          </>
        );
      case "UIB":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> services@uibindia.com</b>
          </>
        );
      case "SRIDHAR":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> motor@sibinsure.com</b>
          </>
        );
      case "POLICYERA":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> support@policyera.com</b>
          </>
        );
      case "TATA":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> support@tmibasl.com</b>
          </>
        );
      case "HEROCARE":
        return (
          <>
            In case of any further requirements, please contact us at
            <b> support@heroibil.com</b>
          </>
        );
        case "VCARE":
          return (
            <>
              In case of any further requirements, please contact us at
              <b> po@vcareinsurance.in</b>
            </>
        );
        case "WOMINGO":
          return (
            <>
              In case of any further requirements, please contact us at
              <b>support@womingo.com</b>
            </>
          );
        case "ONECLICK":
          return (
            <>
              In case of any further requirements, please contact us at
              <b>support@1clickpolicy.com</b>
            </>
        )
      default:
        return (
          <>
            In case of any challenges, please contact us at
            <b> {brokerEmailFunction()}</b> or call us at our number
            <b> {ContactFn()}</b>
          </>
        );
    }
  }
};

// site login url
export const UrlFn = (login, ut) => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      if (login)
        return `https://ola-dashboard.fynity.in/${
          ut ? "employee" : "pos"
        }/login`;
      else return "https://ola-dashboard.fynity.in/";
    case "FYNTUNE":
      return "";
    case "ABIBL":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-preprod-carbike.fynity.in/api"
      ) {
        if (login)
          return `http://preprod-dasbhoard-abibl.fynity.in/${
            ut ? "employee" : "pos"
          }/login`;
        else return "http://preprod-dasbhoard-abibl.fynity.in/";
      } else if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-carbike.fynity.in/api"
      ) {
        if (login)
          return `http://uat-dasbhoard-abibl.fynity.in/${
            ut ? "employee" : "pos"
          }/login`;
        else return "http://uat-dasbhoard-abibl.fynity.in/";
      } else {
        if (login)
          return `http://abibl-prod-dashboard.fynity.in/${
            ut ? "employee" : "pos"
          }/login`;
        else return "http://abibl-prod-dashboard.fynity.in/";
      }
    case "GRAM":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apigramcover-carbike.fynity.in/api"
      ) {
        if (login)
          return `http://uat-dasbhoard-gramcover.fynity.in/${
            ut ? "employee" : "pos"
          }/login`;
        else return "http://uat-dasbhoard-gramcover.fynity.in/";
      } else {
        if (login)
          return `https://dashboard.gramcover.com/${
            ut ? "employee" : "pos"
          }/login`;
        else return "https://dashboard.gramcover.com/";
      }
    case "ACE":
      return "https://crm.aceinsurance.com:5555/";
    case "SRIYAH":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://nammacover.com/"
        : "https://uat.nammacover.com/";
    case "RB":
      return window.location.hostname.includes("renewbuyinsurance")
        ? "https://www.renewbuyinsurance.com/"
        : "https://www.renewbuy.com/";
    case "SPA":
      if (login)
        return `https://uatdashboard.insuringall.com/${
          ut ? "employee" : "pos"
        }/login`;
      else return "https://uatdashboard.insuringall.com/";
    case "BAJAJ":
      if (login) return Bajaj_rURL();
      else return "https://www.bajajcapitalinsurance.com/";
    case "UIB":
      return "";
    case "SRIDHAR":
      if (login)
        return `https://uatdashboard.sibinsure.com/${
          ut ? "employee" : "pos"
        }/login`;
      else return "https://uatdashboard.sibinsure.com/";
    case "POLICYERA":
      if (login)
        return import.meta.env.VITE_PROD === "YES"
          ? `https://dashboard.policyera.com/${ut ? "employee" : "pos"}/login`
          : `https://uatdashboard.policyera.com/${
              ut ? "employee" : "pos"
            }/login`;
      else
        return import.meta.env.VITE_PROD === "YES"
          ? "https://dashboard.policyera.com/"
          : "https://uatdashboard.policyera.com/";
    case "TATA":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://lifekaplan.com/"
        : "https://uat.lifekaplan.com/";
    case "HEROCARE":
      return import.meta.env.VITE_PROD === "YES"
        ? !window.location.href.includes("preprod")
          ? `https://dashboard.heroinsurance.com/${
              ut ? "employee" : "pos"
            }/login`
          : `https://preproddashboard.heroinsurance.com/${
              ut ? "employee" : "pos"
            }/login`
        : `https://uatdashboard.heroinsurance.com/${
            ut ? "employee" : "pos"
          }/login`;
    case "PAYTM":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://posp.paytminsurance.co.in/posp/dashboard/${
            ut ? "employee" : "pos"
          }/login`
        : `https://posp-nonprod.paytminsurance.co.in/dashboard/${
            ut ? "employee" : "pos"
          }/login`;
    case "ONECLICK":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://uat1click.fynity.in/ma/api"
      ) {
        if (login)
          return `https://uat1click.fynity.in/dashboard/${
            ut ? "employee" : "pos"
          }/login`;
        else return "https://uat1click.fynity.in/dashboard/";
      } else {
        if (login)
          return `https://1click.fynity.in/dashboard/${
            ut ? "employee" : "pos"
          }/login`;
        else return "https://1click.fynity.in/dashboard/";
      }
    default:
      return '/';
  }
};

//bajaj url
export const Bajaj_rURL = (b2c) => {
  if (
    import.meta.env.VITE_API_BASE_URL ===
    "https://uatapimotor.bajajcapitalinsurance.com/api"
  ) {
    return b2c
      ? window.location.origin.includes("uat")
        ? "https://dev.bajajcapitalinsurance.com"
        : window.location.origin
      : "https://partneruat.bajajcapitalinsurance.com";
  }
  if (
    import.meta.env.VITE_API_BASE_URL ===
    "https://stageapimotor.bajajcapitalinsurance.com/api"
  ) {
    return b2c
      ? window.location.origin
      : "https://partnerstage.bajajcapitalinsurance.com";
  }
  if (
    import.meta.env.VITE_API_BASE_URL ===
    "https://apimotor.bajajcapitalinsurance.com/api"
  ) {
    return b2c
      ? window.location.origin
      : "https://partner.bajajcapitalinsurance.com";
  }
};

// URLs for HEADER  BAJAJ

export const HeaderUrlFn = (ut, token, seller_type) => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return `https://ola-dashboard.fynity.in/${ut ? "employee" : "pos"}/login`;
    case "FYNTUNE":
      return "";
    case "ABIBL":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-preprod-carbike.fynity.in/api"
      ) {
        return `http://preprod-dasbhoard-abibl.fynity.in/${
          ut ? "employee" : "pos"
        }/login`;
      } else if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-carbike.fynity.in/api"
      ) {
        return `http://uat-dasbhoard-abibl.fynity.in/${
          ut ? "employee" : "pos"
        }/login`;
      } else {
        return `http://abibl-prod-dashboard.fynity.in/${
          ut ? "employee" : "pos"
        }/login`;
      }
    case "GRAM":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apigramcover-carbike.fynity.in/api"
      ) {
        return `http://uat-dasbhoard-gramcover.fynity.in/${
          ut ? "employee" : "pos"
        }/login`;
      } else {
        return `https://dashboard.gramcover.com/${
          ut ? "employee" : "pos"
        }/login`;
      }
    case "ACE":
      return "https://crm.aceinsurance.com:5555/";
    case "SRIYAH":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://nammacover.com/"
        : "https://uat.nammacover.com/";
    case "RB":
      return window.location.hostname.includes("renewbuyinsurance")
        ? "https://www.renewbuyinsurance.com/"
        : "https://www.renewbuy.com/";
    case "SRIDHAR":
      return "https://www.sibinsure.com/";
    case "SPA":
      return `https://uatdashboard.insuringall.com/${
        ut ? "employee" : "pos"
      }/login`;
    case "BAJAJ":
      return Bajaj_HeaderURL(token, seller_type);
    case "TATA":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://dashboard.lifekaplan.com/${ut ? "employee" : "pos"}/login`
        : `https://uatdashboard.lifekaplan.com/${
            ut ? "employee" : "pos"
          }/login`;
    case "POLICYERA":
      return "https://policyera.com/";
    case "HEROCARE":
      return import.meta.env.VITE_PROD === "YES"
        ? !window.location.href.includes("preprod")
          ? `https://dashboard.heroinsurance.com/${
              seller_type === "E"
                ? "employee"
                : seller_type === "P"
                ? "pos"
                : "customer"
            }/login`
          : `https://preproddashboard.heroinsurance.com/${
              seller_type === "E"
                ? "employee"
                : seller_type === "P"
                ? "pos"
                : "customer"
            }/login`
        : `https://uatdashboard.heroinsurance.com/${
            seller_type === "E"
              ? "employee"
              : seller_type === "P"
              ? "pos"
              : "customer"
          }/login`;
    case "KMD":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://dashboard.kmdastur.com/misp/login`
        : `https://uatdashboard.kmdastur.com/misp/login`;
    case "PAYTM":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://posp.paytminsurance.co.in/posp/dashboard/${
            ut ? "employee" : "pos"
          }/login`
        : `https://posp-nonprod.paytminsurance.co.in/dashboard/${
            ut ? "employee" : "pos"
          }/login`;
          case "ONECLICK":
            if (
              import.meta.env?.VITE_API_BASE_URL ===
              "https://uat1click.fynity.in/ma/api"
            ) {
              return `https://uat1click.fynity.in/dashboard/${
                ut ? "employee" : "pos"
              }/login`;
            } else {
              return `https://1click.fynity.in/dashboard/${
                ut ? "employee" : "pos"
              }/login`;
            }
    default:
      return `/`;
  }
};

export const Bajaj_HeaderURL = (token, seller_type) => {
  let sellerType = seller_type
    ? seller_type === "P"
      ? "pos"
      : "employee"
    : "";
  return import.meta.env.VITE_PROD === "YES"
    ? import.meta.env.VITE_BASENAME === "general-insurance"
      ? token
        ? "https://dashboard.bajajcapitalinsurance.com/customer/login"
        : window.location.origin
      : seller_type
      ? `https://dashboard.bajajcapitalinsurance.com/${sellerType}/dashboard`
      : "https://partner.bajajcapitalinsurance.com"
    : import.meta.env.VITE_API_BASE_URL ===
      "https://stageapimotor.bajajcapitalinsurance.com/api"
    ? import.meta.env.VITE_BASENAME === "general-insurance"
      ? token
        ? "https://stagedashboard.bajajcapitalinsurance.com/customer/login"
        : window.location.origin
      : seller_type
      ? `https://stagedashboard.bajajcapitalinsurance.com/${sellerType}/dashboard`
      : "https://partnerstage.bajajcapitalinsurance.com"
    : //UAT
    import.meta.env.VITE_BASENAME === "general-insurance"
    ? token
      ? "https://uatdashboard.bajajcapitalinsurance.com/customer/login"
      : "https://buypolicyuat.bajajcapitalinsurance.com/"
    : seller_type
    ? `https://uatdashboard.bajajcapitalinsurance.com/${sellerType}/dashboard`
    : "https://partneruat.bajajcapitalinsurance.com";
};

// GST
export const DefaultGSTCondition = (brokerValue) => {
  return ["ABIBL", "ACE", "RB", "OLA", "GRAM", "SPA","VCARE","WOMINGO", "ONECLICK"].includes(brokerValue)
    ? "Yes"
    : "No";
  // master ON/OFF condition for config
};

// CPA
export const DefaultCPACondition = (brokerValue, odOnly) => {
  return ["ABIBL", "RB", "OLA", "BAJAJ", "SPA", "POLICYERA","VCARE","WOMINGO","ONECLICK"].includes(
    brokerValue
  ) && odOnly
    ? "Yes"
    : "No";
};

//------------ getting nvb calculation---------------

export const getCalculatedNcb = (yearDiff) => {
  switch (yearDiff) {
    case 0:
      return "0%";
    case 1:
      return "0%";
    case 2:
      return "20%";
    case 3:
      return "25%";
    case 4:
      return "35%";
    case 5:
      return "45%";
    case 6:
      return "50%";
    case 7:
      return "50%";
    case 8:
      return "50%";

    default:
      return "0%";
  }
};

export const getNewNcb = (ncb) => {
  switch (ncb) {
    case "0%":
      return "20%";
    case "20%":
      return "25%";
    case "25%":
      return "35%";
    case "35%":
      return "45%";
    case "45%":
      return "50%";
    case "50%":
      return "50%";
    default:
      return "0%";
  }
};
