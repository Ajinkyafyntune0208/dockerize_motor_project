import React, { useState, useEffect } from "react";
import { Encrypt, isB2B } from "utils";
import { yupResolver } from "@hookform/resolvers/yup";
import { useSelector, useDispatch } from "react-redux";
import { useLocation } from "react-router";
import PropTypes from "prop-types";
import swal from "sweetalert";
import _ from "lodash";
import { useMediaPredicate } from "react-media-hook";
import { useForm } from "react-hook-form";
import { GlobalStyle } from "./style";
import { quoteOption, whatsappContent, yupValidate } from "./helper";
import "./sendQuote.scss";
import Popup from "../Popup";
//prettier-ignore
import { quotePageShareData, quotePageWhatsappData, compareEmailData, compareWhatsappData,
         paymentEmailData, paymentWhatsappData, premiumWhatsappData, premiumEmailData, compareSmsData, compareSmsWhatsappData
       } from "./data";
//prettier-ignore
import { EmailPdf, setEmailPdf, Whatsapp,
         EmailComparePdf, setEmailComparePdf
       } from "modules/quotesPage/quote.slice";
import {
  ShareQuote,
  Prefill,
  TriggerWhatsapp,
  share,
} from "modules/Home/home.slice";
import { MobileDrawer } from "./MobileDrawer";
import { ContentMsg, ContentMsg2 } from "./content/Content";
import { TypeReturn } from "modules/type";
import { EvaluateChannels } from "components/dynamicShare/dynamicShare-logic";

export const SendQuotes = (props) => {
  //prettier-ignore
  const { show, onClose, sendPdf, setSendPdf, comparePdfData, type,
          shareQuotesFromToaster, setShareQuotesFromToaster, premiumBreakuppdf, selectedGarage, openGarageModal, garage,
          shareProposalPayment, setShareProposalPayment
        } = props
  const dispatch = useDispatch();
  const [selectAll, setSelectAll] = useState(false);
  const [msg, setMsg] = useState(false);
  const [whatsappSucess, setWhatsappSucess] = useState(false);
  const [sendAllChannels, setSendAllChannels] = useState(false);
  const [smsSuccess, setSmsSuccess] = useState(false);
  const [disabledBtn, setDisabledBtn] = useState(true);
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const enquiry_id = query.get("enquiry_id");
  const loc = location.pathname ? location.pathname.split("/") : "";
  const { policy } = useSelector((state) => state.payment);
  const { temp_data } = useSelector((state) => state.proposal);
  const {
    temp_data: userDataHome,
    gstStatus,
    theme_conf,
  } = useSelector((state) => state.home);
  //prettier-ignore
  const { finalPremiumlist1, shortTerm, selectedTab, emailPdf,
          customLoad, emailComparePdf, 
        } = useSelector((state) => state.quotes);
  const lessthan576 = useMediaPredicate("(max-width: 576px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan600 = useMediaPredicate("(max-width: 600px)");

  const { handleSubmit, register, errors, watch, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  const [disEmail, setDisEmail] = useState(true);
  const [isActive, setActive] = useState("custom");
  const MobileNo = watch("mobileNo");
  const unformattedEmails = watch("multiEmails");
  const EmailsId = unformattedEmails && unformattedEmails.split(",");
  const [selectedItems, setSelectedItems] = useState([]);
  const [showModal, setShowModal] = useState(false);

  //Prefill Api
  useEffect(() => {
    if (enquiry_id && !_.isEmpty(loc) && loc[2] === "proposal-page")
      dispatch(Prefill({ enquiryId: enquiry_id }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);
  const isPos = isB2B(temp_data, true)?.sellerType === "P";
  //whatsapp redirection through theme config
  // const handleSubConditions = window.location.href.includes("proposal-page")
  //   ? shareProposalPayment
  //     ? "proposal_payment"
  //     : "proposal"
  //   : sendPdf
  //   ? "premium_breakup"
  //   : "quote";
  const handleSubConditions = window.location.href.includes("proposal-page")
    ? shareProposalPayment
      ? "proposal_payment"
      : "proposal"
    : sendPdf
    ? "premium_breakup"
    : window.location.href.includes("payment-success")
    ? "payment-success"
    : "quote";
  const enable_whatsapp_redirection =
    EvaluateChannels(theme_conf, "whatsapp_redirection", handleSubConditions) ||
    theme_conf?.vahanConfig?.whatsapp_redirection_pos === "REDIRECT";

  // share function
  const onSubmit = (data, typeCheck, sendAll) => {
    // quote list
    const getQuoteList = () => {
      if (!_.isEmpty(selectedItems)) {
        let shared = selectedItems.map((item) => item?.companyAlias).join(",");
        if(shared){
          return selectedItems.map(({ idv, name, logo, finalPremium, finalPremiumNoGst, gst, productName, policyId, policyType, applicableAddons, companyAlias}) => ({
            name,
            idv: idv * 1 ? idv : "NA",
            logo,
            finalPremium,
            finalPremiumNoGst: Math.round(finalPremiumNoGst),
            premium: Math.round(finalPremiumNoGst),
            premiumWithGst: finalPremium,
            gst,
            action: `${window.location.href}&productId=${Encrypt(policyId)}${selectedTab === "tab2" ? `&selectedType=${Encrypt(selectedTab)}` : ""}${shortTerm && selectedTab !== "tab2" ? `&selectedTerm=${Encrypt(shortTerm)}` : ""}${!_.isEmpty(shared) ? `&shared=${Encrypt(shared)}` : ""}`,
            productName,
            policyType,
            applicableAddons: applicableAddons,
            companyAlias: companyAlias
          }));
        }
        // prettier-ignore
      } else if (!_.isEmpty(finalPremiumlist1)) {
        let shared = finalPremiumlist1
          .map((item) => item?.companyAlias)
          .join(",");
        return finalPremiumlist1.map((x) => ({
          ...x,
          premium: Math.round(x?.finalPremiumNoGst),
          premiumWithGst: x?.finalPremium * 1 ? Math.round(x?.finalPremium) : 0,
          // prettier-ignore
          action: `${window.location.href}&productId=${Encrypt(x?.policyId)}${selectedTab === "tab2" ? `&selectedType=${Encrypt(selectedTab)}` : ""}${shortTerm && selectedTab !== "tab2" ? `&selectedTerm=${Encrypt(shortTerm)}` : ""}${!_.isEmpty(shared) ? `&shared=${Encrypt(shared)}` : ""}`,
          productName: x?.productName,
          policyType: x?.policyType,
          applicableAddons: x?.applicableAddons,
          companyAlias: x?.companyAlias,
        }));
      }
      return [];
    };

    if (!sendPdf && loc[1] !== "payment-success") {
      if (typeCheck !== 2) {
        sendAll && setSendAllChannels(true);
        typeCheck === 1 && setSmsSuccess(true);
        // quote and proposal sharing function through sms, email, and all channels
        // prettier-ignore
        dispatch(ShareQuote(quotePageShareData(token, userDataHome, temp_data, EmailsId, MobileNo, sendAll, 
          enquiry_id, typeCheck, loc, getQuoteList(), gstStatus)));
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      } else {
        if (MobileNo) {
          if (MobileNo?.length < 10) {
            swal("Info", "Please enter a valid mobile number", "info");
            setValue("mobile", "");
          } else {
            let shared = selectedItems
              .map((item) => item?.companyAlias)
              .join(",");
            // WhatsApp redirection into a new tab
            if (enable_whatsapp_redirection) {
              let content = whatsappContent("some quotes", temp_data, shared);
              if (loc[2] === "proposal-page") {
                content = whatsappContent("a proposal form", temp_data);
              }
              window.open(
                `https://api.whatsapp.com/send?phone=${`91${MobileNo}`}&text=${encodeURIComponent(
                  content
                )}#`
              );
            } else {
              let shared = selectedItems
                .map((item) => item?.companyAlias)
                .join(",");
              // quote and proposal sharing function through WhatsApp
              // prettier-ignore
              dispatch(
                Whatsapp(
                  quotePageWhatsappData(
                    token,
                    userDataHome,
                    temp_data,
                    MobileNo,
                    shareQuotesFromToaster,
                    loc,
                    enquiry_id,
                    getQuoteList(),
                    gstStatus,
                    shared
                  )
                )
              );
              setWhatsappSucess(true);
              setMsg(true);
              if (setShareQuotesFromToaster) {
                setShareQuotesFromToaster(false);
              }
            }
          }
        } else {
          swal("Info", "Please enter your mobile number", "info");
        }
      }
    } else if (loc[2] === "compare-quote") {
      if (EmailsId && typeCheck !== 2 && typeCheck !== 1) {
        // prettier-ignore
        dispatch(EmailComparePdf(compareEmailData(token, userDataHome, temp_data, comparePdfData,
        EmailsId, enquiry_id, typeCheck, MobileNo, gstStatus)));
        sendAll && setSendAllChannels(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      } else if (MobileNo && typeCheck === 1) {
        // prettier-ignore
        dispatch(ShareQuote(compareSmsData(MobileNo, enquiry_id, temp_data, comparePdfData, userDataHome, token)));
        sendAll && setSendAllChannels(true);
        setSmsSuccess(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      } else if (MobileNo && typeCheck === 2) {
        if (!enable_whatsapp_redirection) {
          // prettier-ignore
          dispatch(Whatsapp(compareWhatsappData(token, userDataHome, temp_data, MobileNo, enquiry_id, comparePdfData, loc, gstStatus)));
          sendAll && setSendAllChannels(true);
          setWhatsappSucess(true);
          setMsg(true);
          setTimeout(() => {
            onClose(false);
            setMsg(false);
          }, 2500);
        } else {
          let shared = selectedItems
            .map((item) => item?.companyAlias)
            .join(",");
          window.open(
            `https://api.whatsapp.com/send?phone=${`91${MobileNo}`}&text=${encodeURIComponent(
              `Please review the comparison of your quotes at the following link: ${
                window.location.href
              }${!_.isEmpty(shared) ? `&shared=${Encrypt(shared)}` : ""}.`
            )}#`
          );
        }
      } else if (MobileNo && typeCheck === 3) {
        console.log("Inside typecheck 3");
        // prettier-ignore
        dispatch(EmailComparePdf(compareSmsWhatsappData(token, userDataHome, temp_data, comparePdfData,
        enquiry_id, typeCheck, MobileNo, gstStatus)));
        sendAll && setSendAllChannels(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      }
    } else if (loc[1] === "payment-success") {
      //redirect to whatsapp on payment success page when user share using whatsapp with download pdf link
      if (enable_whatsapp_redirection && isPos) {
        let whatsappRed = true; // sending key in whatsapp redirection case on payment success page only
        let content = whatsappContent(
          "my policy PDF",
          temp_data,
          false,
          whatsappRed,
          policy?.pdfUrl
        );
        console.log(content, "content");
        window.open(
          `https://api.whatsapp.com/send?phone=${`91${MobileNo}`}&text=${encodeURIComponent(
            content
          )}#`
        );
      }
      // prettier-ignore
      if (typeCheck !== 2 && enquiry_id && temp_data?.selectedQuote?.productName && policy?.policyNumber) {
        // prettier-ignore
        dispatch(ShareQuote(paymentEmailData(enquiry_id, sendAll, typeCheck, EmailsId, 
        userDataHome, temp_data, policy, MobileNo, gstStatus)));
        sendAll && setSendAllChannels(true);
        typeCheck === 1 && setSmsSuccess(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      } else if (MobileNo && typeCheck === 2 && !enable_whatsapp_redirection) {
        // prettier-ignore
        dispatch(TriggerWhatsapp(paymentWhatsappData(enquiry_id, userDataHome, temp_data, MobileNo, policy, gstStatus)));
        setWhatsappSucess(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      }
    } else {
      if (MobileNo && typeCheck === 2) {
        // prettier-ignore
        dispatch(Whatsapp(premiumWhatsappData(token, userDataHome, temp_data, MobileNo,
        premiumBreakuppdf, enquiry_id, sendPdf, gstStatus)));
        sendAll && setSendAllChannels(true);
        setWhatsappSucess(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      } else if (EmailsId) {
        // prettier-ignore
        dispatch(EmailPdf(premiumEmailData(token, userDataHome, temp_data, sendPdf, 
        EmailsId, enquiry_id, loc, getQuoteList(), finalPremiumlist1, typeCheck, MobileNo, gstStatus)));
        sendAll && setSendAllChannels(true);
        setMsg(true);
        setTimeout(() => {
          onClose(false);
          setMsg(false);
        }, 2500);
      }
    }
  };

  //OnEmail Pdf success /Error
  useEffect(() => {
    return () => {
      dispatch(setEmailPdf("emailPdf"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [emailPdf]);

  // onMailComparePdf
  useEffect(() => {
    return () => {
      dispatch(setEmailComparePdf("emailPdf"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [emailComparePdf]);
  //---drawer for mobile
  const [drawer, setDrawer] = useState(false);
  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [show]);

  //options for multiselect.
  const options = quoteOption(finalPremiumlist1);
  const options2 = !_.isEmpty(finalPremiumlist1) ? finalPremiumlist1 : [];

  useEffect(() => {
    (options.length && selectedItems.length) !== 0 &&
    options.length === selectedItems.length
      ? setSelectAll(true)
      : setSelectAll(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedItems]);

  //prefill
  useEffect(() => {
    (userDataHome?.mobileNo ||
      userDataHome?.userProposal?.additonalData?.owner?.mobileNumber ||
      temp_data?.userProposal?.additonalData?.owner?.mobileNumber) &&
      setValue(
        "mobileNo",
        userDataHome?.mobileNo
          ? userDataHome?.mobileNo
          : userDataHome?.userProposal?.additonalData?.owner?.mobileNumber
          ? userDataHome?.userProposal?.additonalData?.owner?.mobileNumber
          : temp_data?.userProposal?.additonalData?.owner?.mobileNumber
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userDataHome, isActive]);

  useEffect(() => {
    if (selectedItems?.length > 0) {
      setDisabledBtn(false);
    } else {
      setDisabledBtn(true);
    }
  }, [selectedItems]);

  const onSubmitCashless = (data) => {
    const cashlessObj = {
      enquiryId: enquiry_id,
      companyAlias: selectedGarage?.companyAlias,
      garageMobileNo: selectedGarage?.mobileNo,
      ...(data?.mobileNo && { mobileNo: data?.mobileNo }),
      garageName: selectedGarage?.garageName,
      garagePincode: selectedGarage?.pincode,
      garageAddress: selectedGarage?.garageAddress,
      notificationType:
        EmailsId && data?.mobileNo ? "all" : EmailsId ? "email" : "sms",
      type: "cashlessGarage",
      firstName: userDataHome?.firstName || temp_data?.firstName || "Customer",
      lastName: userDataHome?.lastName || temp_data?.lastName || " ",
      productName: temp_data?.selectedQuote?.productName || TypeReturn(type),
      domain: `http://${window.location.hostname}`,
      ...(EmailsId && { emailId: EmailsId }),
    };
    dispatch(ShareQuote(cashlessObj));
    setMsg(true);
    setTimeout(() => {
      onClose(false);
      setMsg(false);
    }, 2500);
  };

  const content = (
    <ContentMsg2
      lessthan600={lessthan600}
      lessthan576={lessthan576}
      type={type}
      sendPdf={sendPdf}
      shareQuotesFromToaster={shareQuotesFromToaster}
      loc={loc}
      isActive={isActive}
      setActive={setActive}
      disabledBtn={disabledBtn}
      selectAll={selectAll}
      options={options}
      lessthan767={lessthan767}
      selectedItems={selectedItems}
      register={register}
      errors={errors}
      watch={watch}
      MobileNo={MobileNo}
      onSubmit={onSubmit}
      setValue={setValue}
      setDisEmail={setDisEmail}
      userDataHome={userDataHome}
      temp_data={temp_data}
      disEmail={disEmail}
      handleSubmit={handleSubmit}
      customLoad={customLoad}
      setSelectedItems={setSelectedItems}
      setSelectAll={setSelectAll}
      options2={options2}
      showModal={showModal}
      setShowModal={setShowModal}
      selectedGarage={selectedGarage}
      onSubmitCashless={onSubmitCashless}
      garage={garage}
      shareProposalPayment={shareProposalPayment}
    />
  );

  const content2 = (
    <ContentMsg
      type={type}
      loc={loc}
      sendPdf={sendPdf}
      whatsappSucess={whatsappSucess}
      smsSuccess={smsSuccess}
      sendAllChannels={sendAllChannels}
      EmailsId={EmailsId}
      garage={garage}
      temp_data={temp_data}
      enquiry_id={enquiry_id}
      shareProposalPayment={shareProposalPayment}
    />
  );

  return !lessthan767 ? (
    <>
      <Popup
        height={msg ? "240px" : "auto"}
        width="640px"
        show={show}
        onClose={onClose}
        content={msg ? content2 : content}
        position="middle"
        zIndexPopup={true}
        outside={sendPdf ? true : false}
        hiddenClose={customLoad ? true : false}
      />
    </>
  ) : (
    <>
      <MobileDrawer
        drawer={drawer}
        setDrawer={setDrawer}
        onClose={onClose}
        setSendPdf={setSendPdf}
        msg={msg}
        openGarageModal={openGarageModal}
        content={content}
        content2={content2}
        shareProposalPayment={shareProposalPayment}
      />
      <GlobalStyle disabledBackdrop={false} />
    </>
  );
};

export default SendQuotes;

// PropTypes
SendQuotes.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
  sendPdf: PropTypes.bool,
  setSendPdf: PropTypes.func,
  comparePdfData: PropTypes.object,
  type: PropTypes.string,
  shareQuotesFromToaster: PropTypes.bool,
  setShareQuotesFromToaster: PropTypes.func,
  premiumBreakuppdf: PropTypes.string,
  shareProposalPayment: PropTypes.bool,
  setShareProposalPayment: PropTypes.func,
};
