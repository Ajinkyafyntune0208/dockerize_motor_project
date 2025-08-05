import React, { useState, useEffect } from "react";
import styled, { createGlobalStyle } from "styled-components";
import PropTypes from "prop-types";
import { useMediaPredicate } from "react-media-hook";
import Popup from "components/Popup/Popup";
import { Row, Col } from "react-bootstrap";
import { set_temp_data } from "modules/Home/home.slice";
import { useDispatch, useSelector } from "react-redux";
import { setTempData } from "../../filterConatiner/quoteFilter.slice";
import "./policyTypePopup.css";
import {
  differenceInDays,
  differenceInMonths,
  addYears,
  subDays,
} from "date-fns";
import { CancelAll } from "modules/quotesPage/quote.slice";
import moment from "moment";
import Drawer from "@mui/material/Drawer";
import { toDate } from "utils";

const PolicyTypePopup = ({
  show,
  onClose,
  setPolicy,
  policyType,
  setPreviousPopup,
  type,
  setToasterPolicyChange,
}) => {
  const todaysDate = moment().format("DD-MM-YYYY");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { tempData } = useSelector((state) => state.quoteFilter);
  const dispatch = useDispatch();
  const { temp_data } = useSelector((state) => state.home);

  let b = moment().format("DD-MM-YYYY");

  //renewal margin
  let c = "01-09-2018";
  let d = temp_data?.vehicleInvoiceDate;
  let e = moment().format("DD-MM-YYYY");
  let f = temp_data?.manfDate;
  let diffDaysOd = d && c && differenceInDays(toDate(d), toDate(c));
  let diffManfDays = d && c && differenceInDays(toDate(f), toDate(e));
  let diffMonthsOdCar = d && e && differenceInMonths(toDate(e), toDate(d));
  let diffDayOd = d && e && differenceInDays(toDate(e), toDate(d));
  //calc days for edge cases in last month of renewal
  let diffDaysOdCar = e && d && differenceInDays(toDate(e), toDate(d));
  //OD time period used for static policy type display
  const staticOd =
    (diffDaysOd >= 0 &&
      diffDayOd > 270 &&
      (diffMonthsOdCar < 60 ||
        (diffMonthsOdCar === 60 && diffDaysOdCar <= 1095)) &&
      type === "bike") ||
    ((diffMonthsOdCar < 36 ||
      (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
      type === "car") ||
    (diffManfDays > 270 &&
      diffDayOd > 1 &&
      diffMonthsOdCar < 9 &&
      type !== "cv");

  const getNewNcb = (ncb) => {
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
        return "20%";
    }
  };

  const onSubmit = (data, singleYear, isMulti) => {
    if (data !== tempData?.policyType) {
      dispatch(CancelAll(true));
    }
    setPolicy(data);
    dispatch(
      setTempData({
        policyType:
          data === "Not sure"
            ? "Not sure"
            : data === "Short Term"
            ? "Comprehensive"
            : data,
        previousPolicyTypeIdentifier: singleYear ? "Y" : "N",
        ...(singleYear && { odOnly: false }),
        isMultiYearPolicy: isMulti ? "Y" : "N",
        previousPolicyTypeIdentifierCode: isMulti
          ? type === "car"
            ? "33"
            : "55"
          : null,
        ...(data === "Not sure" && {isPopupShown: "N"})
      })
    );

    if (data === "Third-party") {
      dispatch(
        set_temp_data({
          ncb: "0%",
          isToastShown: "Y",
          prevYearNcb: "0%",
          newNcb: "0%",
          noClaimMade: temp_data?.noClaimMade ? temp_data?.noClaimMade : true,
          odOnly: staticOd,
          breakIn:
            differenceInDays(
              toDate(b),
              toDate(
                temp_data?.expiry === "New" ? todaysDate : temp_data?.expiry
              )
            ) > 0
              ? true
              : false,
          expiry:
            !singleYear &&
            staticOd &&
            ((temp_data?.previousPolicyTypeIdentifier === "Y" &&
              temp_data?.policyType === "Third-party") ||
              data === "Third-party")
              ? moment(
                  addYears(
                    subDays(
                      new Date(
                        new Date(
                          `${
                            (
                              temp_data?.vehicleInvoiceDate ||
                              temp_data?.regDate
                            )?.split("-")[2]
                          }`,
                          `${
                            (
                              temp_data?.vehicleInvoiceDate ||
                              temp_data?.regDate
                            )?.split("-")[1] *
                              1 -
                            1
                          }`,
                          `${
                            (
                              temp_data?.vehicleInvoiceDate ||
                              temp_data?.regDate
                            )?.split("-")[0]
                          }`
                        )
                      ),
                      1
                    ),
                    type === "car" ? 3 : 5
                  )
                ).format("DD-MM-YYYY")
              : temp_data?.expiry === "New"
              ? todaysDate
              : temp_data?.expiry,
          ...(temp_data?.expiry === "New" && { prevIc: "Not selected" }),
          prevShortTerm: 0,
          previousPolicyTypeIdentifier: singleYear ? "Y" : "N",
          ...(singleYear && { odOnly: false }),
          isMultiYearPolicy: isMulti ? "Y" : "N",
          previousPolicyTypeIdentifierCode: isMulti
            ? type === "car"
              ? "33"
              : "55"
            : null,
          ...(staticOd &&
            ((tempData?.policyType === "Third-party" &&
              temp_data?.previousPolicyTypeIdentifier === "Y" &&
              !singleYear) ||
              (tempData?.policyType === "Third-party" &&
                temp_data?.previousPolicyTypeIdentifier !== "Y" &&
                singleYear) ||
              tempData?.policyType !== "Third-party") && {
              isExpiryModified: "Y",
            }),
        })
      );
      if (temp_data?.expiry === "New") {
        setToasterPolicyChange(true);
      }
    } else if (
      data === "Comprehensive" ||
      data === "Own-damage" ||
      data === "Short Term"
    ) {
      dispatch(
        set_temp_data({
          ncb:
            temp_data?.ncb && temp_data?.ncb.replace(/%/g, "") * 1
              ? temp_data?.ncb
              : "0%",
          isToastShown: "Y",
          newNcb: temp_data?.newCar
            ? "0%"
            : temp_data?.noClaimMade && !temp_data?.carOwnership
            ? data === "Short Term"
              ? temp_data?.ncb && temp_data?.ncb.replace(/%/g, "") * 1
                ? temp_data?.ncb
                : "0%"
              : temp_data?.newNcb && temp_data?.newNcb.replace(/%/g, "") * 1
              ? temp_data?.prevShortTerm * 1
                ? getNewNcb(temp_data?.ncb)
                : temp_data?.newNcb
              : "20%"
            : "0%",
          breakIn:
            differenceInDays(
              toDate(b),
              toDate(
                temp_data?.expiry === "New" ? todaysDate : temp_data?.expiry
              )
            ) > 0
              ? true
              : false,
          expiry: temp_data?.expiry === "New" ? todaysDate : temp_data?.expiry,
          ...(temp_data?.expiry === "New" && { prevIc: "Not selected" }),
          prevShortTerm: data === "Short Term" ? 1 : 0,
          previousPolicyTypeIdentifier: singleYear ? "Y" : "N",
          ...(singleYear ? { odOnly: false } : { odOnly: staticOd }),
          isMultiYearPolicy: isMulti ? "Y" : "N",
          previousPolicyTypeIdentifierCode: isMulti
            ? type === "car"
              ? "33"
              : "55"
            : null,
          ...(staticOd &&
            ((tempData?.policyType === "Comprehensive" &&
              singleYear &&
              temp_data?.previousPolicyTypeIdentifier !== "Y") ||
              (tempData?.policyType === "Comprehensive" &&
                !singleYear &&
                temp_data?.previousPolicyTypeIdentifier === "Y") ||
              tempData?.policyType !== data) && {
              isExpiryModified: "Y",
            }),
        })
      );
      if (temp_data?.expiry === "New") {
        setToasterPolicyChange(true);
      }
    } else if (data === "Not sure") {
      dispatch(
        set_temp_data({
          ncb: "0%",
          isToastShown: "Y",
          expiry: "New",
          noClaimMade: true,
          policyExpired: true,
          prevYearNcb: "0%",
          prevIc: "New",
          prevIcFullName: "New",
          leadJourneyEnd: true,
          newNcb: "0%",
          isPopupShown: "N",
          breakIn: true,
          prevShortTerm: 0,
          odOnly: false,
          previousPolicyTypeIdentifier: singleYear ? "Y" : "N",
          isMultiYearPolicy: isMulti ? "Y" : "N",
          previousPolicyTypeIdentifierCode: isMulti
            ? type === "car"
              ? "33"
              : "55"
            : null,
        })
      );
    }
    dispatch(CancelAll(false));
    onClose(false);
  };

  //---drawer for mobile5

  const [drawer, setDrawer] = useState(false);

  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
  }, [show]);

  const bundledPolicy =
    (staticOd ||
      (diffMonthsOdCar >= 34 &&
        diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        (diffMonthsOdCar < 36 ||
          (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
        type === "car") ||
      (diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        diffMonthsOdCar >= 58 &&
        (diffMonthsOdCar < 60 ||
          (diffMonthsOdCar === 60 && diffDaysOdCar <= 1095)) &&
        type === "bike")) &&
    (new Date().getFullYear() -
      Number(
        temp_data?.vehicleInvoiceDate?.slice(
          temp_data?.vehicleInvoiceDate?.length - 4
        )
      ) >=
      1 ||
      (diffMonthsOdCar > 9 && diffMonthsOdCar <= 12));

  //Invoice/Registration date
  const invoiceOrRegDate = temp_data?.vehicleInvoiceDate
    ? temp_data?.regDate &&
      Number(
        temp_data?.vehicleInvoiceDate?.slice(
          temp_data?.vehicleInvoiceDate?.length - 4
        )
      ) <
        new Date().getFullYear() - 1
    : temp_data?.regDate &&
      Number(temp_data?.regDate?.slice(temp_data?.regDate?.length - 4)) <
        new Date().getFullYear() - 1;

  const carRenewalMargin =
    diffDaysOd >= 0 &&
    diffMonthsOdCar >= 34 &&
    diffDayOd > 270 &&
    (diffMonthsOdCar < 36 ||
      (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
    type === "car";

  const bikeRenewalMargin =
    diffDaysOd >= 0 &&
    diffMonthsOdCar >= 58 &&
    diffDayOd > 270 &&
    (diffMonthsOdCar < 60 ||
      (diffMonthsOdCar === 60 && diffDaysOdCar <= 1095)) &&
    type === "bike";

  //Determine TP Policy
  const _determineTpPolicy =
    (invoiceOrRegDate && staticOd) || carRenewalMargin || bikeRenewalMargin
      ? `${type === "car" ? "3" : "5"}-Year TP Policy`
      : "Third-party Policy";

  const _determineSingleYearTP =
    invoiceOrRegDate && (staticOd || carRenewalMargin || bikeRenewalMargin);

  const checkReg2019 =
    invoiceOrRegDate &&
    (temp_data?.vehicleInvoiceDate
      ? Number(
          temp_data?.vehicleInvoiceDate?.slice(
            temp_data?.vehicleInvoiceDate?.length - 4
          )
        ) === 2019
      : Number(temp_data?.regDate?.slice(temp_data?.regDate?.length - 4)) ===
        2019);

  const content = (
    <>
      <ContentWrap>
        <ContentTitle>What type of policy did you buy last year?</ContentTitle>
        <ContentSubTitle>
          It will help us to provide accurate quotes for you
        </ContentSubTitle>
        <ExpertForm>
          <form id="confirmPolicyForm" action="">
            <div className="homeInsuInput">
              <div className="homeInsuInputWrap">
                <Row>
                  {/*==========Third-Party==========*/}
                  <Col
                    xl={4}
                    lg={6}
                    md={12}
                    sm={12}
                    style={{ margin: "4px 0 4px 0" }}
                  >
                    <OptionCard
                      onClick={() => {
                        onSubmit("Third-party");
                      }}
                    >
                      <input
                        type="checkbox"
                        name="confirmPolicy"
                        value="Third-party"
                        checked={
                          policyType === "Third-party" &&
                          tempData?.policyType === "Third-party" &&
                          temp_data?.previousPolicyTypeIdentifier !== "Y"
                        }
                        id="tp1"
                      />
                      <label for="tp1"></label>
                      <span></span>
                      {/* <span className="checkmark"></span> */}
                      {/* <span className="smokingTxt"> */}
                      <div className="heading">{_determineTpPolicy}</div>
                      {/* </span> */}
                      <div className="valuntaryDisTxt">
                        Offers protection against damages to the third-party by
                        the insured vehicle.
                      </div>
                      {/* </label> */}
                    </OptionCard>
                  </Col>
                  {/*===xx=====Third-Party=====xx===*/}
                  {/*==========Third-Party 1yr==========*/}
                  {/* This option should be available only in SAOD and in non renewal margin period  */}
                  {_determineSingleYearTP && (
                    <Col
                      xl={4}
                      lg={6}
                      md={12}
                      sm={12}
                      style={{ margin: "4px 0 4px 0" }}
                    >
                      <OptionCard
                        onClick={() => {
                          onSubmit("Third-party", true);
                        }}
                      >
                        <input
                          type="checkbox"
                          name="confirmPolicy"
                          value="Third-party"
                          checked={
                            policyType === "Third-party" &&
                            tempData?.policyType === "Third-party" &&
                            temp_data?.previousPolicyTypeIdentifier === "Y"
                          }
                          id="tp2"
                        />
                        <label for="tp2"></label>
                        <span></span>
                        <div className="heading">1-Year Third-party</div>
                        <div className="valuntaryDisTxt">
                          Offers protection against damages to the third-party
                          by the insured vehicle.
                        </div>
                        {/* </label> */}
                      </OptionCard>
                    </Col>
                  )}
                  {/*===xx=====Third-Party 1yr=====xx===*/}
                  {/*==========Comprehensive/Bundled==========*/}
                  <Col
                    xl={4}
                    lg={6}
                    md={12}
                    sm={12}
                    style={{ margin: "4px 0 4px 0" }}
                  >
                    <OptionCard
                      onClick={() =>
                        bundledPolicy
                          ? onSubmit("Comprehensive")
                          : staticOd || carRenewalMargin || bikeRenewalMargin
                          ? onSubmit("Own-damage")
                          : onSubmit("Comprehensive")
                      }
                    >
                      <input
                        type="checkbox"
                        name="confirmPolicy"
                        value="Bundled Policy"
                        checked={
                          bundledPolicy
                            ? policyType === "Comprehensive" &&
                              tempData?.policyType === "Comprehensive" &&
                              temp_data?.previousPolicyTypeIdentifier !== "Y" &&
                              temp_data?.isMultiYearPolicy !== "Y" &&
                              !(temp_data?.prevShortTerm * 1) &&
                              true
                            : staticOd ||
                              (diffDaysOd &&
                                diffMonthsOdCar &&
                                type &&
                                carRenewalMargin) ||
                              (diffDaysOd &&
                                diffMonthsOdCar &&
                                type &&
                                bikeRenewalMargin)
                            ? tempData?.policyType === "Own-damage" &&
                              temp_data?.previousPolicyTypeIdentifier !== "Y" &&
                              temp_data?.isMultiYearPolicy !== "Y" &&
                              !(temp_data?.prevShortTerm * 1) &&
                              true
                            : (policyType === "Comprehensive" ||
                                tempData?.policyType === "Comprehensive") &&
                              temp_data?.isMultiYearPolicy !== "Y" &&
                              !(temp_data?.prevShortTerm * 1) &&
                              true
                        }
                        id="Comprehensive1"
                      />
                      <label for="Comprehensive1"></label>
                      <span></span>
                      <div className="heading">
                        {bundledPolicy
                          ? "Bundled Policy"
                          : staticOd || carRenewalMargin || bikeRenewalMargin
                          ? "Own-damage"
                          : "Comprehensive"}
                      </div>
                      <div className="valuntaryDisTxt">
                        {temp_data?.regDate &&
                        Number(
                          temp_data?.regDate?.slice(
                            temp_data?.regDate?.length - 4
                          )
                        ) >= 2018 &&
                        (staticOd || carRenewalMargin || bikeRenewalMargin)
                          ? bundledPolicy
                            ? type === "car"
                              ? "1- Year Own Damage + 3-Year Third Party coverage"
                              : "1- Year Own Damage + 5-Year Third Party coverage"
                            : ""
                          : "Policy with 1 year Own Damage and 1 year Third Party "}
                      </div>
                    </OptionCard>
                  </Col>
                  {/*===xx=====Comprehensive/Bundled=====xx===*/}
                  {/*========== 1+1 Comprehensive ==========*/}
                  {/* This will be available in car/bike but not for 2nd renewal in car & fourth renewal in bike  */}
                  {/* checkpoint*/}
                  {(temp_data?.vehicleInvoiceDate || temp_data?.regDate) &&
                    Number(
                      (
                        temp_data?.vehicleInvoiceDate || temp_data?.regDate
                      )?.slice(
                        (temp_data?.vehicleInvoiceDate || temp_data?.regDate)
                          ?.length - 4
                      )
                    ) <
                      new Date().getFullYear() - 1 &&
                    staticOd &&
                    type !== "cv" && (
                      <Col
                        xl={4}
                        lg={6}
                        md={12}
                        sm={12}
                        style={{ margin: "4px 0 4px 0" }}
                      >
                        <OptionCard
                          onClick={() => {
                            onSubmit("Comprehensive", true);
                          }}
                        >
                          <input
                            type="checkbox"
                            name="confirmPolicy"
                            value="Comprehensive"
                            checked={
                              (policyType === "Comprehensive" ||
                                tempData?.policyType === "Comprehensive") &&
                              temp_data?.previousPolicyTypeIdentifier === "Y" &&
                              temp_data?.isMultiYearPolicy !== "Y" &&
                              !(temp_data?.prevShortTerm * 1) &&
                              true
                            }
                            id="Comprehensive2"
                          />
                          <label
                            for="Comprehensive2"
                            style={{ marginTop: lessthan993 ? "10px" : "" }}
                          ></label>
                          <span></span>
                          <div className="heading">Comprehensive</div>
                          <div className="valuntaryDisTxt">
                            {"1- Year Own Damage + 1-Year Third Party coverage"}
                          </div>
                        </OptionCard>
                      </Col>
                    )}
                  {/*========== 1+1 Comprehensive ==========*/}
                  {/*========== 3+3/5+5 Comprehensive ==========*/}
                  {/* This will be available in car/bike only at or before 2019 */}
                  {checkReg2019 && staticOd && type !== "cv" && false && (
                    <Col
                      xl={4}
                      lg={6}
                      md={12}
                      sm={12}
                      style={{ margin: "4px 0 4px 0" }}
                    >
                      <OptionCard
                        onClick={() => {
                          onSubmit("Comprehensive", true, true);
                        }}
                      >
                        <input
                          type="checkbox"
                          name="confirmPolicy"
                          value="Comprehensive"
                          checked={
                            (policyType === "Comprehensive" ||
                              tempData?.policyType === "Comprehensive") &&
                            temp_data?.previousPolicyTypeIdentifier === "Y" &&
                            temp_data?.isMultiYearPolicy === "Y"
                          }
                          id="Comprehensive3"
                        />
                        <label
                          for="Comprehensive3"
                          style={{ marginTop: lessthan993 ? "10px" : "" }}
                        ></label>
                        <span></span>
                        <div className="heading">Bundled Policy</div>
                        <div className="valuntaryDisTxt">
                          {`Policy with ${
                            type === "car" ? "3 OD + 3TP" : "5 OD + 5TP"
                          }`}
                        </div>
                      </OptionCard>
                    </Col>
                  )}
                  {/*========== 3+3/5+5 Comprehensive ==========*/}
                  {/*==========Own-Damage==========*/}
                  {invoiceOrRegDate &&
                    (staticOd || carRenewalMargin || bikeRenewalMargin) && (
                      <Col
                        xl={4}
                        lg={6}
                        md={12}
                        sm={12}
                        style={{ margin: "4px 0 4px 0" }}
                      >
                        <OptionCard onClick={() => onSubmit("Own-damage")}>
                          <input
                            type="checkbox"
                            name="confirmPolicy"
                            value="Own-damage Policy"
                            checked={
                              policyType === "Own-damage" ||
                              tempData?.policyType === "Own-damage"
                            }
                            id="Own-damage1"
                          />
                          <label
                            for="Own-damage1"
                            style={{ marginTop: lessthan993 ? "10px" : "" }}
                          ></label>
                          <span></span>
                          <div className="heading">1-Year OD only</div>
                          <div className="valuntaryDisTxt">
                            Covers damages to your Vehicle only and not third
                            party.
                          </div>
                        </OptionCard>
                      </Col>
                    )}
                  {/*===xx=====Own-Damage=====xx===*/}
                  {/*==========Short Term==========*/}
                  {["ACE", "FYNTUNE", "OLA", "HEROCARE"].includes(
                    import.meta.env.VITE_BROKER
                  ) &&
                    type === "cv" &&
                    ["PCV", "GCV"].includes(temp_data?.journeyCategory) && (
                      <Col
                        xl={4}
                        lg={6}
                        md={12}
                        sm={12}
                        style={{ margin: "4px 0 4px 0" }}
                      >
                        <OptionCard
                          onClick={() => {
                            onSubmit("Short Term");
                          }}
                        >
                          <input
                            type="checkbox"
                            name="confirmPolicy"
                            value="Short Term"
                            checked={temp_data?.prevShortTerm * 1}
                            id="Short-Term1"
                          />
                          <label
                            for="Short-Term1"
                            style={{ marginTop: lessthan993 ? "10px" : "" }}
                          ></label>
                          <span></span>
                          <div className="heading">Short Term</div>
                        </OptionCard>
                      </Col>
                    )}
                  {/*===xx=====Short Term=====xx===*/}
                  {/*========== Not Sure ==========*/}
                  {!temp_data?.newCar && (
                    <Col
                      xl={4}
                      lg={6}
                      md={12}
                      sm={12}
                      style={{ margin: "4px 0 4px 0" }}
                    >
                      <OptionCard
                        onClick={() => {
                          onSubmit("Not sure");
                        }}
                      >
                        <input
                          type="checkbox"
                          name="confirmPolicy"
                          value="Not sure"
                          checked={
                            policyType === "Not sure" ||
                            tempData?.policyType === "Not sure" ||
                            tempData?.policyType === "New"
                          }
                          id="Not sure"
                        />
                        <label
                          for="Not sure"
                          style={{ marginTop: lessthan993 ? "10px" : "" }}
                        ></label>
                        <span></span>
                        <div className="heading">Not Sure About Policy</div>
                        {/* </label> */}
                      </OptionCard>
                    </Col>
                  )}
                  {/*===xx===== Not Sure =====xx===*/}
                </Row>
              </div>
            </div>
          </form>
        </ExpertForm>
      </ContentWrap>
    </>
  );
  return !lessthan767 ? (
    <Popup
      height={"auto"}
      width={"700px"}
      show={show}
      onClose={onClose}
      content={content}
      position={lessthan993 ? "bottom" : "center"}
      hiddenClose={tempData?.policyType ? false : true}
    />
  ) : (
    <>
      <React.Fragment
        key={"bottom"}
        style={{ borderRadius: "5% 5% 0% 0%", overflow: "hidden" }}
      >
        <Drawer
          anchor={"bottom"}
          open={drawer}
          onClose={() => {
            setDrawer(false);
            onClose(false);
          }}
          onOpen={() => setDrawer(true)}
          ModalProps={{
            keepMounted: true,
          }}
        >
          <MobileDrawerBody>
            <CloseButton
              onClick={() => {
                setDrawer(false);
                onClose(false);
              }}
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: " 25px" }}
              >
                <path
                  fill={"#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </CloseButton>
            {content}
          </MobileDrawerBody>
        </Drawer>
      </React.Fragment>

      <GlobalStyle />
    </>
  );
};

// PropTypes
PolicyTypePopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
PolicyTypePopup.defaultProps = {
  show: false,
  onClose: () => {},
};

const GlobalStyle = createGlobalStyle`
body {
	.MuiDrawer-paperAnchorBottom {
		border-radius: 3% 3% 0px 0px;
		z-index: 99999 !important;
	}
	.css-1u2w381-MuiModal-root-MuiDrawer-root {
    z-index: 100000 !important;
  }
}
`;
const ContentWrap = styled.div`
  padding: 0px 32px 30px 32px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 14px;
  line-height: 22px;
  color: #333;
  position: relative;
  margin-top: 30px;
`;
const ContentTitle = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 20px;
  line-height: 20px;
  margin-bottom: 8px;
  font-weight: 900;
`;
const ContentSubTitle = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 14px;
  line-height: 22px;
  color: #808080;
  margin-bottom: 24px;
`;
const ExpertForm = styled.div`
  margin: 0 0 35px 0;
  form {
    display: flex;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"Inter-Medium"`};
    font-size: 15px;
    line-height: 24px;
  }
  .smokingTxt {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  }
  .valuntaryDisTxt {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  }
`;

const MobileDrawerBody = styled.div`
  width: 100%;
  border-radius: 3px 3px 0px 0px;
  overflow: auto;
  @media (max-width: 767px) {
    ::-webkit-scrollbar {
      display: none !important;
    }
  }
  .ratioButton {
    margin: 0 !important;
    padding-top: 0px !important;
  }
`;
const OptionCard = styled.div`
  cursor: pointer;
  display: flex;
  // justify-content: center;
  // align-items: center;
  flex-direction: column;
  padding: 10px;
  width: 100%;
  box-shadow: 0px 6px 16px #3469cb29;
  width: 100%;
  font-weight: 400;
  margin: 0 0 4px 0;
  border-radius: 16px;
  margin-top: 10px;
  height: 130px;
  position: relative;
  span {
    z-index: -1;
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    border-radius: 16px;
  }
  input[type="checkbox"]:checked ~ span {
    background-color: ${({ theme }) =>
      `${theme.NoPlanCard?.background1 || "#fefff5"} !important`};
    border: ${({ theme }) =>
      `${
        theme.NoPlanCard?.border1 || "2px solid rgb(189, 212, 0)"
      } !important`};
  }
  label {
    background-color: #fff;
    border: 1px solid #ccc;
    border-radius: 50%;
    cursor: pointer;
    height: 28px;
    right: -10px;
    position: absolute;
    top: -10px;
    width: 28px;
  }

  label:after {
    border: 2px solid #fff;
    border-top: none;
    border-right: none;
    content: "";
    height: 6px;
    left: 7px;
    opacity: 0;
    position: absolute;
    top: 8px;
    transform: rotate(-45deg);
    width: 12px;
  }

  input[type="checkbox"] {
    visibility: hidden;
  }

  input[type="checkbox"]:checked + label {
    background-color: ${({ theme }) =>
      `${theme.QuoteCard?.color || "rgb(189, 212, 0)"} !important`};
    // background-color: #66bb6a;
    border-color: #66bb6a;
  }

  input[type="checkbox"]:checked + label:after {
    opacity: 1;
  }
  .smokingTxt {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  }
  .heading {
    margin-top: 8px;
    font-size: 12.5px;
    font-weight: 600;
    padding: 0px 4px;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
    @media (max-width: 767px) {
      font-size: 11.5px;
    }
    @media (max-width: 410px) {
      font-size: 11.5px;
      margin: 0px 0 4px 0;
    }
  }
  .valuntaryDisTxt {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  }
  .subHeading {
    margin-top: 8px;
    font-size: 12.5px;
    font-weight: 400;
    padding: 0px 4px;

    @media (max-width: 767px) {
      // text-align: center;
      font-size: 13px;
    }
    @media (max-width: 410px) {
      font-size: 12.5px;
    }
  }
  :hover {
    transform: scale(1.01);
  }
  @media (max-width: 767px) {
    padding: 12px;
    height: 100px;
  }
`;
const CloseButton = styled.div`
  display: ${({ hiddenClose }) => (hiddenClose ? "none" : "block")};
  position: absolute;
  top: 10px;
  right: 10px;
  cursor: pointer;
  z-index: 1111;
  &:hover {
    text-decoration: none;
    color: #363636;
  }
`;

export default PolicyTypePopup;
