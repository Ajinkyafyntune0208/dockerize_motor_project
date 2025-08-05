import React, { useState, useEffect } from "react";
import styled, { createGlobalStyle } from "styled-components";
import PropTypes from "prop-types";
import { Row, Form } from "react-bootstrap";
import { useForm } from "react-hook-form";
import tooltip from "../../../../assets/img/tooltip.svg";
import CustomTooltip from "components/tooltip/CustomTooltip";
import Popup from "components/Popup/Popup";
import { useDispatch, useSelector } from "react-redux";
import _, { parseInt } from "lodash";
import { numOnly, _haptics } from "utils";
import "./idvPopup.scss";
import { setTempData } from "../../filterConatiner/quoteFilter.slice";
import { useMediaPredicate } from "react-media-hook";
import { currencyFormater } from "utils";
import { set_temp_data } from "modules/Home/home.slice";
import Drawer from "@mui/material/Drawer";
import SelectIdvSection from "./SelectIdvSection";
//prettier-ignore
import { ApplyButton, CloseButton, Conatiner, GlobalStyle, MobileDrawerBody, PaymentTermTitle, PopupSubHead, PopupSubTitle } from "./IdvPopupStyle";

const IDVPopup = ({ show, onClose, idv, setIDV, quote }) => {
  const dispatch = useDispatch();
  const { register, watch, errors } = useForm();
  const lessthan963 = useMediaPredicate("(max-width: 963px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { tempData } = useSelector((state) => state.quoteFilter);
  const { theme_conf } = useSelector((state) => state.home);
  const [availQuotesInRange, setAvailQuotesInRange] = useState(0);
  const [acronymCurrency, setacronymCurrency] = useState("0.00");
  const getLowestIdv = () => {
    let Min = _.minBy(quote, "minIdv");
    return parseInt(Min?.minIdv);
  };

  // change the value to k,lac & Cr format
  const acronymformatValue = (inputValue) => {
    if (inputValue >= 10000000) {
      return (inputValue / 10000000).toFixed(6).toString().slice(0, -4) + " Cr";
    } else if (inputValue >= 100000) {
      return (inputValue / 100000).toFixed(6).toString().slice(0, -4) + " Lac";
    } else if (inputValue >= 1000) {
      return (inputValue / 1000).toFixed(6).toString().slice(0, -4) + "k";
    } else {
      return inputValue;
    }
  };

  const calculateRangedQuotes = (inputValue) => {
    if (inputValue <= getHighestIdv() && inputValue >= getLowestIdv()) {
      const rangedQuotes = quote?.filter(
        (item) =>
          item?.minIdv * 1 <= inputValue && inputValue <= item?.maxIdv * 1
      );
      return rangedQuotes.length;
    } else {
      return 0;
    }
  };

  //  IDV input handler for the entered value formatter
  const handleInputChange = (e) => {
    let inputValue = e.target.value.replace(/\D/g, "").substring(0, 9);
    if (inputValue === "" || isNaN(inputValue) || inputValue <= 999) {
      setacronymCurrency("0.00");
    } else if (parseFloat(inputValue) <= 100000001) {
      setacronymCurrency(acronymformatValue(parseFloat(inputValue)));
    } else {
      inputValue = "100000000";
      setacronymCurrency(acronymformatValue(100000000));
    }
    e.target.value = inputValue;
    setAvailQuotesInRange(calculateRangedQuotes(inputValue));
  };

  const getHighestIdv = () => {
    let Max = _.maxBy(quote, "maxIdv");
    return parseInt(Max?.maxIdv);
  };

  const getAverageIdv = () => {
    let filteredQuote = quote?.map((item) =>
      Number(item?.idv) ? Number(item?.idv) : 0
    );
    let newFilterQuote = filteredQuote.filter((cv) => cv != 0);
    let Avg = _.meanBy(newFilterQuote);
    return parseInt(Avg);
  };

  const SelectedIdv = watch("idvType");
  const CustomIdv = watch("customIdv");
  const [idvError, setIdvError] = useState(false);

  //validate custom IDV
  useEffect(() => {
    if (SelectedIdv === "ownIDV") {
      if (
        CustomIdv > getHighestIdv() ||
        CustomIdv < getLowestIdv() ||
        !CustomIdv
      ) {
        setIdvError("Please Enter IDV in specified Range");
        setacronymCurrency(acronymformatValue(CustomIdv));
        setAvailQuotesInRange(0);
      } else {
        setIdvError(false);
        setAvailQuotesInRange(calculateRangedQuotes(CustomIdv));
        setacronymCurrency(acronymformatValue(CustomIdv));
      }
    } else {
      setIdvError(false);
      setAvailQuotesInRange(calculateRangedQuotes(CustomIdv));
      setacronymCurrency(acronymformatValue(CustomIdv));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [SelectedIdv, CustomIdv]);

  const onSubmit = (data) => {
    dispatch(
      setTempData({
        idvChoosed: SelectedIdv === "ownIDV" ? CustomIdv : getIDV(SelectedIdv),
        idvType: SelectedIdv,
      })
    );
    dispatch(
      set_temp_data({
        isOdDiscountApplicable: "Y",
      })
    );

    onClose(false);
  };

  //prefill
  const getIDV = (idvType) => {
    switch (idvType) {
      case "avgIdv":
        return getAverageIdv();
      case "lowIdv":
        return getLowestIdv();
      case "highIdv":
        return getHighestIdv();
      default:
        return "0";
    }
  };

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

  const idvSelected = theme_conf?.common_config?.idv_settings;

  const content = (
    <>
      <Conatiner>
        <Row>
          <PaymentTermTitle>
            Insured Declared Value (IDV)
            <span
              className="cardTooltipSvg"
              data-toggle="popover"
              title=""
              data-content="Insured Declared Value (IDV) Text"
              data-original-title="Insured Declared Value (IDV)"
            >
              <CustomTooltip
                rider="true"
                id="RiderInbuilt__Tooltip"
                place={"bottom"}
                customClassName="mt-3 riderPageTooltip"
                allowClick
              >
                <img
                  data-tip="<h3 >Insured Value (IDV)</h3> <div>Insured Declared Value (IDV) is the maximum amount your insurer can provide you in case your car is stolen or totally damaged subject to IDV guidelines. Note: IDV should be 10% less than previous year's IDV, as per the depreciation norms of Indian Motor Tariff. Insurers consider the same for total loss or theft claims.</div>"
                  data-html={true}
                  data-for="RiderInbuilt__Tooltip"
                  src={tooltip}
                  alt="tooltip"
                  className="toolTipRiderChild"
                />
              </CustomTooltip>
            </span>
          </PaymentTermTitle>
          <PopupSubTitle>
            IDV is the maximum value that you get in case of total loss or theft
            of your vehicle.
          </PopupSubTitle>
          <PopupSubHead>Choose your IDV value:</PopupSubHead>
          <SelectIdvSection
            register={register}
            SelectedIdv={SelectedIdv}
            idvSelected={idvSelected}
            tempData={tempData}
            getLowestIdv={getLowestIdv}
            getHighestIdv={getHighestIdv}
            currencyFormater={currencyFormater}
            idvError={idvError}
            errors={errors}
            numOnly={numOnly}
            handleInputChange={handleInputChange}
            acronymCurrency={acronymCurrency}
            availQuotesInRange={availQuotesInRange}
          />
          <div className="paymentTermRadioWrap">
            <ApplyButton
              disabled={idvError}
              onClick={() => [_haptics([100, 0, 50]), onSubmit()]}
            >
              APPLY
            </ApplyButton>
          </div>
        </Row>
      </Conatiner>
    </>
  );
  return !lessthan767 ? (
    <Popup
      height={"auto"}
      width="360px"
      show={show}
      onClose={onClose}
      content={content}
      position="center"
      top="45%"
      left={lessthan963 ? "50%" : "65%"}
    />
  ) : (
    <>
      <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
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
IDVPopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
IDVPopup.defaultProps = {
  show: false,
  onClose: () => {},
};

export default IDVPopup;
