import React, { useEffect } from "react";
import _ from "lodash";
import {
  ContactImg,
  ContactText,
  Container,
  Content2,
  FlexDiv,
  Laxmi,
  LaxmiWrapper,
  MainWrapper,
  MessageContainer,
  NextBtn,
  NextContainer,
  ShareCheckBox,
  Text,
  Wrapper,
} from "../style";
import { TypeReturn } from "modules/type";
// prettier-ignore
import {  Breadcrumb } from "react-bootstrap";
import { currencyFormater } from "utils";
import { QrModal } from "components/modal/qr-modal";
import ShareForm from "./ShareForm";
import { _deliveryTracking } from "analytics/proposal-tracking/payment-modal-tracking";

export const ContentMsg = ({
  type,
  loc,
  sendPdf,
  whatsappSucess,
  smsSuccess,
  sendAllChannels,
  EmailsId,
  temp_data,
  shareProposalPayment
}) => {

  useEffect(() => {
    //Analytics | proposal pdf tracking
    if (
      window.location.href.includes("/proposal-page") &&
      !_.isEmpty(temp_data)
    ) {
      const channel = smsSuccess
        ? "SMS"
        : whatsappSucess
        ? "Whatsapp"
        : sendAllChannels
        ? "All"
        : "Email";
      _deliveryTracking(type, temp_data, channel);
    }
  }, []);
  return (
    <MessageContainer>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="#4fcc6e"
        width="48px"
        height="48px"
      >
        <path d="M0 0h24v24H0z" fill="none"></path>
        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path>
      </svg>
      <FlexDiv>
        <LaxmiWrapper>
          <Laxmi
            src={
              TypeReturn(type) !== "bike"
                ? `${
                    import.meta.env.VITE_BASENAME !== "NA"
                      ? `/${import.meta.env.VITE_BASENAME}`
                      : ""
                  }/assets/images/new-car.svg`
                : `${
                    import.meta.env.VITE_BASENAME !== "NA"
                      ? `/${import.meta.env.VITE_BASENAME}`
                      : ""
                  }/assets/images/vehicle/bike3.png`
            }
            alt="Laxmi"
          />
        </LaxmiWrapper>
        <Wrapper>
          <Text>
            Thank you! Your{" "}
            {loc[1] === "payment-success"
              ? "policy has been sent"
              : loc[2] === "proposal-page"
              ? "proposal has been sent"
              : loc[1] === "payment-success"
              ? "policy has been sent"
              : !sendPdf
              ? "quote(s) have been sent"
              : loc[2] === "compare-quote" && sendAllChannels
              ? "comparision of quote from selected ICs has been sent "
              : loc[2] === "compare-quote" && smsSuccess
              ? "compare page url sent"
              : "pdf has been sent"}
            {whatsappSucess
              ? import.meta.env.VITE_BROKER === "BAJAJ"
                ? " to your WhatsApp successfully."
                : " through Whatsapp successfully."
              : smsSuccess
              ? " through SMS successfully"
              : sendAllChannels
              ? import.meta.env.VITE_BROKER === "BAJAJ"
                ? ` to email, What's app & SMS successfully.`
                : " through all channels successfully."
              : ` on given Email${
                  EmailsId && !_.isEmpty(EmailsId) && EmailsId?.length === 1
                    ? " successfully."
                    : "(s) successfully."
                }`}
          </Text>
        </Wrapper>
      </FlexDiv>
    </MessageContainer>
  );
};

export const ContentMsg2 = ({
  lessthan600,
  lessthan576,
  type,
  sendPdf,
  shareQuotesFromToaster,
  loc,
  isActive,
  setActive,
  disabledBtn,
  selectAll,
  options,
  lessthan767,
  selectedItems,
  register,
  errors,
  watch,
  MobileNo,
  onSubmit,
  setValue,
  setDisEmail,
  userDataHome,
  temp_data,
  disEmail,
  handleSubmit,
  customLoad,
  setSelectedItems,
  setSelectAll,
  options2,
  setShowModal,
  showModal,
  shareProposalPayment,
}) => {
  const checkItem = (item) => {
    let allItems = [...selectedItems, item];
    setSelectedItems(allItems);
  };

  const removeItem = (item) => {
    let allItems = selectedItems.filter((x) => x.policyId !== item.policyId);
    setSelectedItems(allItems);
  };

  const selectAllItems = () => {
    let allItems = options2;
    setSelectedItems(allItems);
    setSelectAll(true);
  };

  const removeAllItems = () => {
    let allItems = [];
    setSelectedItems(allItems);
    setSelectAll(false);
  };

  const handleShare = () => {
    if (selectedItems?.length > 0) {
      setActive("shareOption");
    }
  };

  return (
    <>
      <MainWrapper>
        <div
          className="sendQuotes"
          style={{
            height:
              loc[2] === "quotes" && !shareQuotesFromToaster && !sendPdf
                ? "400px"
                : "auto",
          }}
        >
          <Wrapper>
            <div
              className="contact__imgbox"
              style={{ display: lessthan600 && "none" }}
            >
              <ContactImg
                src={
                  TypeReturn(type) !== "bike"
                    ? `${
                        import.meta.env.VITE_BASENAME !== "NA"
                          ? `/${import.meta.env.VITE_BASENAME}`
                          : ""
                      }/assets/images/new-car.jpg`
                    : `${
                        import.meta.env.VITE_BASENAME !== "NA"
                          ? `/${import.meta.env.VITE_BASENAME}`
                          : ""
                      }/assets/images/vehicle/bike2.png`
                }
                alt="Lakshmi"
              />
            </div>
            <ContactText style={{ display: lessthan600 && "none" }}>
              {!sendPdf && shareQuotesFromToaster !== true ? (
                <p
                  style={{
                    fontSize: lessthan576 ? "0.9rem" : "",
                  }}
                >
                  {isActive === "custom" && loc[2] !== "proposal-page"  ? (
                    "Please choose the quotes you'd like to share."
                  ) : (
                    <>
                      Hi, please choose the way you wish to share the{" "}
                      {loc[2] === "proposal-page"
                        ? "proposal"
                        : loc[2] === "payment-confirmation"
                        ? "payment"
                        : loc[1] === "payment-success"
                        ? "policy"
                        : "quotes"}
                    </>
                  )}
                </p>
              ) : shareQuotesFromToaster ? (
                <p>Hi, Please share mobile number of customer. </p>
              ) : (
                <p>
                  Please enter your Email Ids{" "}
                  {(import.meta.env.VITE_BROKER === "ACE" ||
                    (import.meta.env.VITE_BROKER === "RB" &&
                      loc[2] === "compare-quote") ||
                    import.meta.env.VITE_BROKER === "BAJAJ") &&
                    "or Mobile No."}{" "}
                  to share PDF.
                </p>
              )}
            </ContactText>
            {loc[2] === "quotes" && !shareQuotesFromToaster && !sendPdf && (
              <Container>
                <Breadcrumb>
                  <Breadcrumb.Item
                    onClick={() => setActive("custom")}
                    active={Boolean(isActive === "custom")}
                  >
                    Quote List
                  </Breadcrumb.Item>
                  <Breadcrumb.Item
                    onClick={handleShare}
                    active={Boolean(isActive === "shareOption")}
                    disable={disabledBtn}
                    className={disabledBtn ? "disabled-breadcrumb-item" : ""}
                  >
                    Basic Details
                  </Breadcrumb.Item>
                  {isActive === "custom" && (
                    <Breadcrumb.Item
                      className="selectAllBtn"
                      onClick={selectAll ? removeAllItems : selectAllItems}
                    >
                      Select all
                      {selectAll ? (
                        <ShareCheckBox
                          onClick={removeAllItems}
                          className="fa fa-check"
                          style={{ marginLeft: "5px" }}
                        ></ShareCheckBox>
                      ) : (
                        <div
                          onClick={selectAllItems}
                          style={{
                            height: "15px",
                            width: "15px",
                            border: "1px solid",
                            cursor: "pointer",
                            marginLeft: "5px",
                          }}
                        ></div>
                      )}
                    </Breadcrumb.Item>
                  )}
                </Breadcrumb>
              </Container>
            )}
            {isActive === "custom" &&
            loc[2] === "quotes" &&
            !shareQuotesFromToaster &&
            !sendPdf ? (
              <>
                <table style={{ width: "100%" }}>
                  {options &&
                    options.map((item, index) => (
                      <tr style={{ marginBottom: "15px" }}>
                        <td>
                          <img
                            src={item.logo}
                            alt="Logo"
                            style={{
                              width: "50px",
                              margin: lessthan767
                                ? "0px 15px 0px 0px"
                                : "0px 15px",
                            }}
                          />
                        </td>
                        <td style={{ lineHeight: "1px", width: "270px" }}>
                          <p
                            style={{ fontSize: lessthan767 ? "12px" : "14px" }}
                          >
                            {item?.name.length > 35
                              ? item?.name?.slice(0, 31) + "..."
                              : item?.name}
                          </p>
                          <small
                            style={{
                              fontSize: lessthan767 ? "10px" : "12px",
                              color: "gray",
                            }}
                          >
                            Policy Type: {item.policyType}
                          </small>
                        </td>
                        <td style={{ display: lessthan767 ? "none" : "" }}>
                          <button
                            style={{
                              fontSize: "12px",
                              border: "none",
                              borderRadius: "10px",
                              padding: "7px 14px",
                              width: "145px",
                              marginRight: "10px",
                            }}
                          >
                            Premium: ₹ {currencyFormater(item.finalPremium)}
                          </button>
                        </td>
                        <td>
                          <div
                            style={{
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "space-evenly",
                            }}
                          >
                            {selectedItems
                              .map((x) => x.policyId)
                              .includes(item?.policyId) ? (
                              <ShareCheckBox
                                onClick={() => removeItem(item)}
                                className="fa fa-check"
                                style={{
                                  marginLeft: lessthan767 ? "-28px" : "5px",
                                }}
                              ></ShareCheckBox>
                            ) : (
                              <div
                                onClick={() => checkItem(item)}
                                style={{
                                  height: "15px",
                                  width: "15px",
                                  border: "1px solid",
                                  cursor: "pointer",
                                  marginLeft: lessthan767 ? "-28px" : "5px",
                                }}
                              ></div>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                </table>
                {!disabledBtn && (
                  <NextContainer isBajaj={true}>
                    <NextBtn onClick={() => setActive("shareOption")}>
                      Next
                    </NextBtn>
                  </NextContainer>
                )}
              </>
            ) : (
              <ShareForm
                lessthan576={lessthan576}
                sendPdf={sendPdf}
                shareQuotesFromToaster={shareQuotesFromToaster}
                loc={loc}
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
                setShowModal={setShowModal}
                shareProposalPayment={shareProposalPayment}
              />
            )}
          </Wrapper>
        </div>
      </MainWrapper>
      <Content2>
        <p>
          <span>*</span>
          {shareQuotesFromToaster
            ? "Customer will receive message on shared WhatsApp number."
            : "Please note that the premium may vary in future."}
        </p>
      </Content2>
      <QrModal
        show={showModal}
        onHide={() => setShowModal(false)}
        sendPdf={sendPdf}
        type={type}
      />
    </>
  );
};
