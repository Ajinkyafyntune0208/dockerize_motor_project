import React, { useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useMediaPredicate } from "react-media-hook";
import { setTempData } from "./quoteFilter.slice";
import { useForm } from "react-hook-form";
import moment from "moment";
import { differenceInDays } from "date-fns";
import { currencyFormater, toDate } from "utils";
import Style from "./style";
import { Col, Row } from "react-bootstrap";
import Skeleton from "react-loading-skeleton";
import { FiEdit } from "react-icons/fi";
import { Switch } from "components";
import IDVPopup from "../quotesPopup/idvPopup/IDVPopup";
import _ from "lodash";

export const Filters = ({
  setSortBy,
  quote,
  gstToggle,
  setGstToggle,
  setDaysToExpiry,
  allQuoteloading,
  setPopupOpen,
  loadingNTooltip,
}) => {
  const dispatch = useDispatch();
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const { temp_data, prefillLoading } = useSelector((state) => state.home);
  const { updateQuoteLoader } = useSelector((state) => state.quotes);

  const getLowestIdv = () => {
    let Min = _.minBy(quote, "idv");
    return parseInt(Min?.idv);
  };

  useEffect(() => {
    !_.isEmpty(quote) &&
      dispatch(
        setTempData({
          minIdv: getLowestIdv(),
        })
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quote]);

  const { tempData } = useSelector((state) => state.quoteFilter);
  const [idvPopup, setIdvPopup] = useState(false);
  const { watch } = useForm({});

  // not relevant now
  const sortBy = watch("sory-by");

  useEffect(() => {
    setSortBy(sortBy?.id);
    dispatch(
      setTempData({
        sortBy: sortBy?.id,
      })
    );
  }, [sortBy]);

  //--------------------prefill idv---------------------------------------
  useEffect(() => {
    if (temp_data?.isIdvChanged) {
      dispatch(
        setTempData({
          idvChoosed: temp_data?.vehicleIdv,
          idvType: temp_data?.vehicleIdvType,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    temp_data?.vehicleIdv,
    temp_data?.vehicleIdvType,
    temp_data?.isIdvChanged,
  ]);

  const [visualIdv, setVisualIdv] = useState(0);

  //-----------------------checking days to expiry----------------------------
  useEffect(() => {
    let b = moment().format("DD-MM-YYYY");
    let c = temp_data?.expiry;
    let diffDaysExpiry = c && b && differenceInDays(toDate(c), toDate(b));
    if (Number(diffDaysExpiry) > 0 && Number(diffDaysExpiry) < 30) {
      setDaysToExpiry(diffDaysExpiry);
    } else {
      setDaysToExpiry(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.expiry]);

  // checking any popup open or not
  useEffect(() => {
    if (idvPopup) {
      setPopupOpen(true);
    } else {
      setPopupOpen(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idvPopup]);

  const isIdvAvailable =
    temp_data?.isOdDiscountApplicable ||
    temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" ||
    temp_data?.tab === "tab2";

  const isIdvPopupAvailable =
    temp_data?.tab !== "tab2" &&
    (temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
      temp_data?.renewalAttributes?.idv);

  return (
    <Style.FilterCont>
      <Row className="d-flex justify-content-end filterPadding">
        <Col sm={12} xl={3} lg={3} md={12}></Col>
        <Col sm={12} xl={3} lg={3} md={12}></Col>
        <Col sm={12} xl={3} lg={3} md={12}>
          {prefillLoading || updateQuoteLoader ? (
            <Style.FilterMenuQuoteBoxWrap
              exp={true}
              // onClick={() => temp_data?.tab !== "tab2" && setIdvPopup(true)}
              style={{ position: "relative", bottom: "6px" }}
            >
              <Skeleton width={236} height={30}></Skeleton>
            </Style.FilterMenuQuoteBoxWrap>
          ) : (
            <Style.FilterMenuQuoteBoxWrap
              exp={true}
              style={{
                ...(lessthan993 && { display: "none" }),
                pointerEvents: allQuoteloading ? "none" : "",
                color: loadingNTooltip && "lightgrey",
              }}
              id="idv-edit"
              onClick={() => isIdvPopupAvailable && setIdvPopup(true)}
            >
              {isIdvPopupAvailable && (
                <Style.FilterTopBoxChange>
                  {allQuoteloading ? (
                    <Style.SpinnerWrapper />
                  ) : (
                    <FiEdit className="blueIcon" />
                  )}
                </Style.FilterTopBoxChange>
              )}
              {/* IDV changes  */}
              {isIdvAvailable ? (
                <Style.FilterTopBoxTitle>
                  {Number(tempData?.idvChoosed) !== getLowestIdv()
                    ? temp_data?.tab === "tab2"
                      ? "IDV"
                      : tempData?.idvType === "highIdv"
                      ? "Highest IDV"
                      : "IDV"
                    : tempData?.idvType === "lowIdv"
                    ? "Lowest IDV"
                    : "IDV"}{" "}
                  :{" "}
                  <span>
                    {" "}
                    {isIdvAvailable
                      ? temp_data?.tab !== "tab2"
                        ? visualIdv !== 0
                          ? `₹ ${currencyFormater(visualIdv)}`
                          : tempData?.idvChoosed
                          ? `₹ ${currencyFormater(tempData?.idvChoosed)}`
                          : `₹ ${currencyFormater(getLowestIdv())}`
                        : "Not Applicable"
                      : "Calculating"}
                  </span>
                </Style.FilterTopBoxTitle>
              ) : (
                <Style.FilterTopBoxTitle>
                  {allQuoteloading ? "Calculating" : "Choose your IDV"}
                </Style.FilterTopBoxTitle>
              )}
            </Style.FilterMenuQuoteBoxWrap>
          )}
        </Col>
        <Col sm={12} xl={2} lg={2} md={12}>
          {prefillLoading || updateQuoteLoader ? (
            <Skeleton
              width={134}
              height={30}
              style={{ display: lessthan993 ? "none" : "inline-block" }}
            ></Skeleton>
          ) : (
            <Switch
              lessthan600={lessthan600}
              value={gstToggle}
              onChange={setGstToggle}
              Content="GST"
              id="gst-switch"
            />
          )}
        </Col>
      </Row>
      {idvPopup && (
        <IDVPopup
          show={idvPopup}
          onClose={setIdvPopup}
          quote={quote}
          visualIdv={visualIdv}
        />
      )}
    </Style.FilterCont>
  );
};
