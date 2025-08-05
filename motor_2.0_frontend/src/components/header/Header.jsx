import React, { useState, useRef, useEffect } from "react";
import CallMe from "components/Popup/callMe/CallMe";
import { SendQuotes } from "components/Popup/sendQuote/SendQuotes";
import { useLocation } from "react-router";
import { useOutsideClick } from "../../hoc";
import { useDispatch, useSelector } from "react-redux";
import { reloadPage } from "../../utils";
import _ from "lodash";
import { RemoveToken } from "modules/login/login.slice";
import { LogoFn, HeaderUrlFn, Bajaj_HeaderURL } from "components";
import { useMediaPredicate } from "react-media-hook";
import { setTempData } from "../../modules/quotesPage/filterConatiner/quoteFilter.slice";
import { TypeReturn } from "modules/type";
import { MdOutlineMessage } from "react-icons/md";
import swal from "sweetalert";
import { Navbar, Logo } from "./HeaderStyle";
import { CCTPContent } from "./CCTPContent";
import ComponentsBreadCrumbs from "components/bread-crumb/bread-crumb";

const Header = (props) => {
  const { theme_conf } = useSelector((state) => state.home);
  const location = useLocation();
  
  const loc = location.pathname ? location.pathname.split("/") : "";
  const type = !_.isEmpty(loc) ? (loc?.length >= 2 ? loc[1] : "") : "";

  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");

  //IOS check.
  let isMobileIOS = false; //initiate as false
  // device detection
  if (
    /iPad|iPhone|iPod/.test(navigator.userAgent) &&
    !window.MSStream &&
    lessthan767
  ) {
    isMobileIOS = true;
  }

  var standalone = window.navigator.standalone,
    userAgent = window.navigator.userAgent.toLowerCase(),
    safari = /safari/.test(userAgent),
    ios = /iphone|ipod|ipad/.test(userAgent);

  const includeRoute = [
    `/${type}/proposal-page`,
    `/${type}/review`,
    `/${type}/quotes`,
    `/${type}/compare-quote`,
    `/payment-success`,
  ];

  const loginIncludeRoute = [
    `/${type}/registration`,
    `/${type}/lead-page`,
    `/${type}/renewal`,
  ];

  const RegistrationRoute = [`/${type}/registration`];

  const includeRouteStickyNavBar = [`/${type}/quotes`];
  const includeRouteShare = [
    `/${type}/quotes`,
    `/${type}/compare-quote`,
    `/${type}/proposal-page`,
    `/payment-success`,
  ];

  const excludeRoute = [
    `/${type}/otp-verification-tata`,
    `/${type}/payment/failed`,
    `/${type}/payment-gateway`,
    `/${type}/404`,
  ];

  const [modal, setModal] = useState(false);
  const [sendQuotes, setSendQuotes] = useState(false);
  const { temp_data, tokenData } = useSelector((state) => state.home);
  const { temp_data: temp, rskycStatus } = useSelector(
    (state) => state.proposal
  );
  const { removeToken } = useSelector((state) => state.login);
  // prettier-ignore
  const {comparePdfData,quoteComprehesive,quotetThirdParty,quoteShortTerm,
    validQuote,quotesLoaded,  } = useSelector((state) => state.quotes);

  const [navCheck, setNavCheck] = useState(false);
  const dispatch = useDispatch();
  const query = new URLSearchParams(location.search);
  const id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const homeJourneyarra = location?.pathname?.split("/");
  const homeJourney =
    homeJourneyarra?.includes("vehicle-details") ||
    homeJourneyarra?.includes("vehicle-type") ||
    homeJourneyarra?.includes("registration") ||
    homeJourneyarra?.includes("renewal");

  //bread-crumbs visibility l;ogic
  const isBreakinCase =
    temp?.userProposal?.isBreakinCase === "Y" ||
    temp_data?.userProposal?.isBreakinCase === "Y";
  const icr = query.get("icr");

  const blockEdit =
    (isBreakinCase && TypeReturn(type) !== "bike") ||
    icr ||
    rskycStatus?.kyc_status;

  useEffect(() => {
    if (removeToken?.redirectionUrl) {
      localStorage.removeItem("rm_token");
      reloadPage(removeToken?.redirectionUrl);
    }
  }, [removeToken]);

  useEffect(() => {
    if (sendQuotes) {
      dispatch(
        setTempData({
          sendQuote: true,
        })
      );
    } else {
      dispatch(
        setTempData({
          sendQuote: false,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sendQuotes]);

  const handleRedirection = () => {
    dispatch(
      RemoveToken({
        enquiryId: id ? id : "NULL",
        token: token,
      })
    );
  };

  const dropDownRef = useRef(null);
  useOutsideClick(dropDownRef, () => setNavCheck(false));

  const filArr = !_.isEmpty(temp_data?.agentDetails)
    ? temp_data?.agentDetails.filter(
        (item) => item.sellerType === "P" || item.sellerType === "E"
      )
    : !_.isEmpty(temp?.agentDetails)
    ? temp?.agentDetails.filter(
        (item) => item.sellerType === "P" || item.sellerType === "E"
      )
    : [];

  const showAgentDetails = () => {
    swal({
      title: "Agent Details",
      text: `      
                ${
                  !_.isEmpty(filArr)
                    ? filArr[0]?.agentName
                    : tokenData?.seller_name
                } \n${
        !_.isEmpty(filArr)
          ? filArr[0]?.userName
            ? filArr[0]?.userName
            : filArr[0]?.agentId
          : tokenData?.user_name
      }`,
    });
  };

  let ut =
    //home state
    (temp_data?.agentDetails &&
      !_.isEmpty(temp_data?.agentDetails) &&
      !_.isEmpty(
        temp_data?.agentDetails?.find((o) => o?.sellerType === "E")
      )) ||
    //proposal state
    (temp?.agentDetails &&
      !_.isEmpty(temp?.agentDetails) &&
      !_.isEmpty(temp?.agentDetails?.find((o) => o?.sellerType === "E"))) ||
    //token data
    tokenData?.usertype === "E" ||
    tokenData?.seller_type === "E" ||
    //b to c
    !token;

  //Is Pos
  let ut2 =
    (temp_data?.agentDetails &&
      !_.isEmpty(temp_data?.agentDetails) &&
      !_.isEmpty(
        temp_data?.agentDetails?.find((o) => o?.sellerType === "E")
      )) ||
    //proposal state
    (temp?.agentDetails &&
      !_.isEmpty(temp?.agentDetails) &&
      !_.isEmpty(temp?.agentDetails?.find((o) => o?.sellerType === "E"))) ||
    (temp_data?.agentDetails &&
      !_.isEmpty(temp_data?.agentDetails) &&
      !_.isEmpty(
        temp_data?.agentDetails?.find((o) => o?.sellerType === "P")
      )) ||
    //proposal state
    (temp?.agentDetails &&
      !_.isEmpty(temp?.agentDetails) &&
      !_.isEmpty(temp?.agentDetails?.find((o) => o?.sellerType === "P")));

  //store POS / Employee
  const filterSeller =
    (temp?.agentDetails &&
      !_.isEmpty(temp?.agentDetails) &&
      temp?.agentDetails.filter((item) =>
        ["P", "E", "U"].includes(item?.sellerType)
      )) ||
    (temp_data?.agentDetails &&
      !_.isEmpty(temp_data?.agentDetails) &&
      temp_data?.agentDetails.filter((item) =>
        ["P", "E", "U"].includes(item?.sellerType)
      ));

  const seller_type =
    (!_.isEmpty(filterSeller) ? filterSeller[0]?.sellerType : false) ||
    (["P", "E", "U"].includes(tokenData?.seller_type) &&
      tokenData?.seller_type);

  let pdfBackground = validQuote?.length > 1 ? "#fff" : "#8080802e" || "";
  let broker = import.meta.env.VITE_BROKER;

  function getHeaderUrl(
    isMobileIOS,
    standalone,
    safari,
    userAgent,
    broker,
    token,
    seller_type,
    ut2,
    temp,
    type
  ) {
    const isMobileIOSAndNotStandalone = isMobileIOS && !standalone && !safari;
    const isWebView = userAgent.includes("wv");
    const isRB = broker === "RB";
    const isSpecificBroker = ["SRIYAH", "POLICYERA", "SRIDHAR"].includes(
      broker
    );
    const isProdEnv = import.meta.env.VITE_PROD === "YES";
    const isPreProdEnv = !window.location.href.includes("preprod");
    const isBajaj = broker === "BAJAJ";
    const isTata = broker === "TATA";
    const isHeroCare = broker === "HEROCARE";
    const isKaro = broker === "KAROINSURE";

    const allLogoUrl = theme_conf?.broker_config;

    if ((isMobileIOSAndNotStandalone || isWebView) && isRB) {
      return `${window.location.href}`;
    } else if (token || isSpecificBroker || ut2) {
      return HeaderUrlFn(false, token, seller_type);
    } else if (isBajaj) {
      return Bajaj_HeaderURL(token, seller_type);
    } else if (isTata) {
      return isProdEnv
        ? "https://lifekaplan.com/"
        : "https://uat.lifekaplan.com/";
    }else if(isHeroCare || isKaro){
      if(seller_type){
        return allLogoUrl?.[seller_type]
      }else{
        return allLogoUrl?.B2C
      }
    }
     else if (!isHeroCare) {
      const productType =
        temp?.productSubTypeId === 1
          ? "car"
          : temp?.productSubTypeId === 2
          ? "bike"
          : type && ["car", "bike", "cv"].includes(TypeReturn(type))
          ? type
          : temp?.journeyCategory
          ? temp?.journeyCategory.toLowerCase() === "pcv" ||
            temp?.journeyCategory.toLowerCase() === "gcv"
            ? "cv"
            : temp?.journeyCategory.toLowerCase()
          : "cv";
      const baseName =
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : "";
      return `${window.location.origin}${baseName}/${productType}/lead-page`;
    } else if (seller_type === "U") {
      return HeaderUrlFn(false, token, seller_type);
    } else {
      return isProdEnv && isPreProdEnv
        ? "https://www.heroinsurance.com/"
        : isPreProdEnv
        ? "https://www.heroinsurance.com/herouat/"
        : "https://www.heroinsurance.com/heropreprod/";
    }
  }

  return (
    <header>
      <Navbar
        RegistrationRoute={RegistrationRoute}
        location={location}
        quotes={includeRouteStickyNavBar?.includes(location.pathname)}
        lessthan767={lessthan767}
        visiblityLogin={
          (location.pathname === `/${type}/lead-page` ||
            location.pathname === `/${type}/registration` ||
            location.pathname === `/${type}/renewal`) &&
          ((!_.isEmpty(temp_data?.agentDetails) &&
            _.isEmpty(
              temp_data?.agentDetails?.filter((o) =>
                ["cse", "qr", "ios"].includes(o?.source)
              )
            ) &&
            _.isEmpty(
              temp_data?.agentDetails?.filter((o) => o?.source === "app")
            ) &&
            _.isEmpty(
              temp_data?.agentDetails?.filter((o) => o?.sellerType === "U")
            )) ||
            _.isEmpty(temp_data?.agentDetails))
        }
        webView={
          (isMobileIOS && !standalone && !safari) || userAgent.includes("wv")
        }
      >
        <div className="d-flex justify-content-center align-items-center">
          <a
            className="logo"
            href={
              theme_conf?.broker_config?.broker_asset?.logo_url?.url ||
              getHeaderUrl(
                isMobileIOS,
                standalone,
                safari,
                userAgent,
                broker,
                token,
                seller_type,
                ut2,
                temp,
                type
              )
            }
          >
            <Logo
              src={
                LogoFn()
              }
              alt="logo"
            />

            {/* <Logo src={ theme_conf?.broker_config?.broker_asset?.logo?.base64 || LogoFn()} alt="logo" /> */}
          </a>
          {theme_conf?.broker_config?.showBreadcrumbs &&
            !blockEdit &&
            !lessthan993 &&
            !lessthan993 && <ComponentsBreadCrumbs />}
        </div>
        <div
          className="d-flex my-auto"
          style={{
            ...(import.meta.env.VITE_BROKER === "RB" &&
              loginIncludeRoute?.includes(location.pathname) && {
                justifyContent: "space-between",
                width: lessthan767 ? "210px" : lessthan360 ? "190px" : "",
              }),
          }}
        >
          {!excludeRoute.includes(location.pathname) && (
            // callUs, ContactUs, Trace ID, PosLogin , PDF content
            <CCTPContent
              lessthan767={lessthan767}
              id={
                temp_data?.traceId
                  ? temp_data?.traceId
                  : temp?.traceId
                  ? temp?.traceId
                  : id
              }
              token={token}
              query={query}
              tokenData={tokenData}
              filArr={filArr}
              showAgentDetails={showAgentDetails}
              lessthan993={lessthan993}
              handleRedirection={handleRedirection}
              lessthan360={lessthan360}
              ut={ut}
              type={type}
              validQuote={validQuote}
              quoteComprehesive={quoteComprehesive}
              quotesLoaded={quotesLoaded}
              quotetThirdParty={quotetThirdParty}
              quoteShortTerm={quoteShortTerm}
              setModal={setModal}
              MdOutlineMessage={MdOutlineMessage}
              comparePdfData={comparePdfData}
              location={location}
              pdfBackground={pdfBackground}
              loc={loc}
              setSendQuotes={setSendQuotes}
              includeRoute={includeRoute}
              includeRouteShare={includeRouteShare}
              homeJourney={homeJourney}
              temp_data={temp_data}
            />
          )}
        </div>
      </Navbar>
      {modal && <CallMe show={modal} onClose={setModal} />}
      {sendQuotes && (
        <SendQuotes
          show={sendQuotes}
          onClose={setSendQuotes}
          sendPdf={loc[2] === "compare-quote" ? true : false}
          comparePdfData={comparePdfData}
          type={type}
        />
      )}
    </header>
  );
};

export default Header;
