import React from "react";
import { Col, Form, Row } from "react-bootstrap";
import { Controller } from "react-hook-form";
import { SingleDatePicker } from "react-dates";
import moment from "moment";
import { Error } from "components";
import { differenceInMonths, toDate } from "date-fns";
import _ from "lodash";

// prettier-ignore
import { Body, ModelWrap, OptionCard, OptionsOdType, Page2, Page3, PrevOdTypeContainer, RegiHeading, TabContinueWrap } from "../style/style";

const PreviousPolicyContent = ({ step, handleSubmit, onSubmit, ...rest }) => {
  // prettier-ignore
  const { temp_data, odOnly, renewalMargin, tempData, lessthan767, control,
          policyMax, policyMin, type, policyMin1, isFocused, onDateChange, onFocusChange,
          errors, register, handleNoPrev, handlePrevPolicySelection
        } = rest;
        
  return (
    <Body id="whole" className="someClass">
      {step === 2 && (
        <Page2 display={step === 2}>
          <Row>
            <ModelWrap>
              <Form onSubmit={handleSubmit(onSubmit)} className="w-100  ">
                {temp_data?.ncb ? (
                  <RegiHeading>
                    {" "}
                    Select Previous{" "}
                    {(temp_data?.odOnly || odOnly || renewalMargin) &&
                      tempData?.policyType !== "Third-party" &&
                      temp_data?.policyType !== "Third-party" &&
                      temp_data?.previousPolicyTypeIdentifier !== "Y" &&
                      "OD"}{" "}
                    Policy Expiry Date
                    {/* What is your policy expiration date ? */}
                  </RegiHeading>
                ) : (
                  <>
                    {" "}
                    <div className="greetings-wrapper">
                      <div className="greetings-text">
                        {" "}
                        Quotes are just a step away.{" "}
                      </div>
                    </div>
                    <RegiHeading>
                      {" "}
                      Select Previous{" "}
                      {(temp_data?.odOnly || odOnly || renewalMargin) &&
                        tempData?.policyType !== "Third-party" &&
                        "OD"}{" "}
                      Policy Expiry Date
                    </RegiHeading>
                  </>
                )}
                <Col
                  xs="12"
                  sm="12"
                  md="12"
                  lg="12"
                  xl="12"
                  className="w-100 mt-4 mx-auto"
                >
                  <div
                    className={`py-2 dateTimeFour date-picker-ico-set w-100 hiddenInput single-date-picker ${
                      lessthan767 ? "mobileDate" : "text-center-picker"
                    }`}
                  >
                    <Controller
                      control={control}
                      name="expiry"
                      defaultValue={
                        temp_data?.expiry &&
                        temp_data?.expiry !== "New" &&
                        !_.isEmpty(temp_data?.expiry?.split("-")) &&
                        temp_data?.expiry?.split("-")?.length > 2
                          ? moment(
                              temp_data?.expiry,
                              "DD-MM-YYYY"
                            ).isSameOrBefore(moment(policyMax)) &&
                            moment(
                              temp_data?.expiry,
                              "DD-MM-YYYY"
                            ).isSameOrAfter(
                              moment(type === "cv" ? policyMin : policyMin1)
                            )
                            ? moment(
                                temp_data?.expiry.split("-").reverse().join("-")
                              )
                            : moment()
                          : moment()
                      }
                      render={({ onChange, onBlur, value, name }) => (
                        <SingleDatePicker
                          id="date_input"
                          key={
                            !temp_data?.regDate
                              ? "key_random_0"
                              : odOnly
                              ? "key_random_1"
                              : "key_random_2"
                          }
                          date={
                            temp_data?.expiry &&
                            temp_data?.expiry !== "New" &&
                            !_.isEmpty(temp_data?.expiry?.split("-")) &&
                            temp_data?.expiry?.split("-")?.length > 2
                              ? moment(
                                  temp_data?.expiry,
                                  "DD-MM-YYYY"
                                ).isSameOrBefore(moment(policyMax)) &&
                                moment(
                                  temp_data?.expiry,
                                  "DD-MM-YYYY"
                                ).isSameOrAfter(
                                  moment(type === "cv" ? policyMin : policyMin1)
                                )
                                ? moment(
                                    temp_data?.expiry
                                      .split("-")
                                      .reverse()
                                      .join("-")
                                  ).add(0, "M")
                                : moment()
                              : moment()
                          }
                          initialVisibleMonth={() =>
                            temp_data?.expiry &&
                            temp_data?.expiry !== "New" &&
                            !_.isEmpty(temp_data?.expiry?.split("-")) &&
                            temp_data?.expiry?.split("-")?.length > 2
                              ? moment(
                                  temp_data?.expiry,
                                  "DD-MM-YYYY"
                                ).isSameOrBefore(moment(policyMax)) &&
                                moment(
                                  temp_data?.expiry,
                                  "DD-MM-YYYY"
                                ).isSameOrAfter(
                                  moment(type === "cv" ? policyMin : policyMin1)
                                )
                                ? moment(
                                    temp_data?.expiry
                                      .split("-")
                                      .reverse()
                                      .join("-")
                                  ).add(0, "M")
                                : moment()
                              : moment()
                          }
                          focused={isFocused}
                          onDateChange={onDateChange}
                          onFocusChange={onFocusChange}
                          // numberOfMonths={2}
                          isOutsideRange={(date) =>
                            date.isAfter(moment(policyMax)) ||
                            date.isBefore(
                              moment(type === "cv" ? policyMin : policyMin1)
                            )
                          }
                          numberOfMonths={lessthan767 ? 1 : 2}
                        />
                      )}
                    />
                    {!!errors?.expiry && (
                      <Error className="mt-1">{errors?.expiry?.message}</Error>
                    )}
                  </div>
                  <input ref={register} name="ncb" type="hidden" />
                </Col>
              </Form>
              <TabContinueWrap>
                <div
                  onClick={() => {
                    handleNoPrev(); //temperory diable
                  }}
                >
                  I don't know the previous policy details.
                </div>
              </TabContinueWrap>
            </ModelWrap>
          </Row>
        </Page2>
      )}
      <Page3 display={step === 1}>
        <PrevOdTypeContainer page3>
          <RegiHeading page3>
            {" "}
            Which cover did you have on your expiring policy?
          </RegiHeading>
          <OptionsOdType>
            {/*========== Bundled only ==========*/}
            {
              <OptionCard
                onClick={() => {
                  handlePrevPolicySelection(
                    "Comprehensive",
                    false,
                    //Assumption for only first renewal
                    false
                    // !(
                    //   !_.isEmpty(temp_data?.vehicleInvoiceDate.split("-")) &&
                    //   temp_data?.vehicleInvoiceDate.split("-")?.length > 2 &&
                    //   ((differenceInMonths(
                    //     toDate(moment().format("DD-MM-YYYY")),
                    //     toDate(temp_data?.vehicleInvoiceDate)
                    //   ) > 24 &&
                    //     type === "car") ||
                    //     (differenceInMonths(
                    //       toDate(moment().format("DD-MM-YYYY")),
                    //       toDate(temp_data?.vehicleInvoiceDate)
                    //     ) > 48 &&
                    //       type === "bike"))
                    // ) &&
                    //   //No assumption on current year - 1year
                    //   !(
                    //     !_.isEmpty(temp_data?.vehicleInvoiceDate.split("-")) &&
                    //     temp_data?.vehicleInvoiceDate.split("-")?.length > 2 &&
                    //     Number(
                    //       temp_data?.vehicleInvoiceDate?.slice(
                    //         temp_data?.vehicleInvoiceDate?.length - 4
                    //       )
                    //     ) ===
                    //       new Date().getFullYear() * 1 - 1
                    //   )
                  );
                }}
              >
                <div className="heading">
                  {temp_data?.vehicleInvoiceDate &&
                  Number(
                    temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
                  ) >= 2018
                    ? type === "car"
                      ? "Bundled 1+3 Policy"
                      : "Bundled 1+5 Policy"
                    : "1-Year Comprehensive/Standard Policy"}
                </div>
                <div className="subHeading">
                  {temp_data?.regDate &&
                  Number(
                    temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
                  ) >= 2018
                    ? type === "car"
                      ? "1- Year Own Damage + 3-Year Third Party coverage"
                      : "1- Year Own Damage + 5-Year Third Party coverage"
                    : "Covers your bike and third party for 1 year"}
                </div>
              </OptionCard>
            }
            {/*====xx===== Bundled only ====xx=====*/}
            {/*========== 1+1 Comprehensive ==========*/}
            {temp_data?.vehicleInvoiceDate &&
              Number(
                temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
              ) <
                new Date().getFullYear() - 1 &&
              (type === "bike" || type === "car") && (
                <OptionCard
                  onClick={() => {
                    handlePrevPolicySelection("Comprehensive", 1);
                  }}
                >
                  <div className="heading">
                    1-Year Comprehensive/Standard Policy
                  </div>
                  <div className="subHeading">
                    Covers your vehicle and third party for 1 year
                  </div>
                </OptionCard>
              )}
            {/*====xx===== 1+1 Comprehensive ====xx=====*/}
            {/*========== Third Party ==========*/}
            <OptionCard
              onClick={() => {
                handlePrevPolicySelection("Third-party");
              }}
            >
              <div className="heading">
                {temp_data?.vehicleInvoiceDate &&
                Number(
                  temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
                ) >= 2018
                  ? type === "car"
                    ? "3-Year Third Party only"
                    : "5-Year Third Party only"
                  : "1-Year Third Party only"}
              </div>
              <div className="subHeading">
                {temp_data?.vehicleInvoiceDate &&
                Number(
                  temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
                ) >= 2018
                  ? type === "car"
                    ? "Covers third party only for 3 year"
                    : "Covers third party only for 5 year"
                  : "Covers third party only for 1 year"}
              </div>
            </OptionCard>
            {/*====xx===== Third Party ====xx=====*/}
            {/*========== 1 Year OD ==========*/}
            {temp_data?.vehicleInvoiceDate &&
              Number(
                temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
              ) <
                new Date().getFullYear() - 1 &&
              (type === "bike" || type === "car") && (
                <OptionCard
                  onClick={() => {
                    handlePrevPolicySelection("Own-damage");
                  }}
                >
                  <div className="heading">1-Year Own Damage only</div>
                  <div className="subHeading">
                    Covers damages to your Vehicle only and not third party.
                  </div>
                </OptionCard>
              )}
            {/*====xx===== 1 Year OD ====xx=====*/}
            {/*========== 1 Year Third Party ==========*/}
            {temp_data?.vehicleInvoiceDate &&
              Number(
                temp_data?.vehicleInvoiceDate?.slice(temp_data?.vehicleInvoiceDate?.length - 4)
              ) <
                new Date().getFullYear() - 1 &&
              (type === "bike" || type === "car") && (
                <OptionCard
                  onClick={() => {
                    handlePrevPolicySelection("Third-party", 1);
                  }}
                >
                  <div className="heading">1-Year Third Party only</div>
                  <div className="subHeading">
                    Covers third party for 1 year.
                  </div>
                </OptionCard>
              )}
            {/*====xx===== 1 Year Third Party ====xx=====*/}
            {/*========== Not Sure ==========*/}
            {temp_data?.vehicleInvoiceDate &&
              (temp_data?.odOnly || odOnly || renewalMargin) &&
              (type === "bike" || type === "car") && (
                <OptionCard
                  onClick={() => {
                    handlePrevPolicySelection("Not sure");
                  }}
                >
                  <div className="heading">Not Sure</div>
                  <div className="subHeading">
                    Not Sure about the type of policy
                  </div>
                </OptionCard>
              )}
          </OptionsOdType>
        </PrevOdTypeContainer>
      </Page3>
    </Body>
  );
};

export default PreviousPolicyContent;
