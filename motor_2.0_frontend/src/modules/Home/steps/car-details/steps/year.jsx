import React, { useEffect } from "react";
import { Tile } from "components";
import { Row, Col, Form } from "react-bootstrap";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import { set_temp_data, SaveQuoteData, clear } from "modules/Home/home.slice";
import { useDispatch, useSelector } from "react-redux";
import moment from "moment";
import { useMediaPredicate } from "react-media-hook";
import { toDate } from "utils";
import { differenceInMonths, differenceInDays } from "date-fns";
import { generateYearSkeletonRows } from "./skeleton";
import { useAutoTrigger } from "./steps-hooks";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";

export const YearCM = ({ stepFn, enquiry_id, type, token, TypeReturn }) => {
  const dispatch = useDispatch();
  const { temp_data, saveQuoteData, stepper1, loading } = useSelector(
    (state) => state.home
  );

  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");

  // validation schema
  const yupValidate = yup.object({
    year: yup.string().required("year is required").nullable(),
    // manfDate: yup.string().required("manufacture date is required").nullable(),
  });

  const { handleSubmit, register, setValue, watch } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  //year array
  const now = new Date().getUTCFullYear();

  let yearArray =
    temp_data?.regNo && temp_data?.regNo[0] * 1
      ? [`20${temp_data?.regNo.slice(0, 2)}`]
      : Array(now - (now - 26))
          .fill("")
          .map((v, idx) => now - idx);

  yearArray =
    Number(new Date().getMonth()) >= 10
      ? ["Brand New", ...yearArray]
      : yearArray;

  //autofocus
  const year = watch("year");

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      //Analytics | Reg Year
      _useMMVTracking("reg-year", temp_data?.regDate, TypeReturn(type));
      stepFn(6, 7);
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteData]);

  //newBuisiness logic
  let b = moment().format("DD-MM-YYYY");
  let c = `${
    Number(new Date().getFullYear()) !==
    Number(year === "Brand New" ? new Date().getFullYear() : year)
      ? `01`
      : moment().format("DD-MM-YYYY").split("-")[0]
  }-${
    year === "Brand New"
      ? moment().format("DD-MM-YYYY").split("-")[1]
      : Number(year) === Number(new Date().getFullYear()) &&
        yearArray.includes("Brand New")
      ? "01"
      : moment().format("DD-MM-YYYY").split("-")[1]
  }-${
    moment(year === "Brand New" ? String(new Date().getFullYear()) : year)
      .format("DD-MM-YYYY")
      .split("-")[2]
  }`;
  let diffMonthsRollOver = c && b && differenceInMonths(toDate(b), toDate(c));

  //SAOD
  const saod = (data) => {
    let b = "01-09-2018";
    let c = data;
    let d = moment().format("DD-MM-YYYY");
    let diffDaysOd = c && b && differenceInDays(toDate(c), toDate(b));
    let diffMonthsOdCar = c && d && differenceInMonths(toDate(d), toDate(c));
    let diffDayOd = c && d && differenceInDays(toDate(d), toDate(c));

    return (
      ((diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        diffMonthsOdCar < 58 &&
        TypeReturn(type) === "bike") ||
        (diffDayOd > 270 &&
          diffMonthsOdCar < 34 &&
          TypeReturn(type) === "car")) &&
      temp_data?.policyType !== "Not sure"
    );
  };

  const onSubmit = (data) => {
    const calc_date = `${
      Number(new Date().getFullYear()) !==
      Number(data?.year === "Brand New" ? new Date().getFullYear() : data?.year)
        ? `01`
        : moment().format("DD-MM-YYYY").split("-")[0]
    }-${
      data?.year === "Brand New"
        ? moment().format("DD-MM-YYYY").split("-")[1]
        : Number(data?.year) === Number(new Date().getFullYear()) &&
          yearArray.includes("Brand New")
        ? "01"
        : moment().format("DD-MM-YYYY").split("-")[1]
    }-${
      moment(
        data?.year === "Brand New"
          ? String(new Date().getFullYear())
          : data?.year
      )
        .format("DD-MM-YYYY")
        .split("-")[2]
    }`;
    dispatch(
      set_temp_data({
        regDate: calc_date,
        vehicleInvoiceDate: calc_date,
        manfDate: `${
          data?.year === "Brand New"
            ? moment().format("DD-MM-YYYY").split("-")[1]
            : Number(data?.year) === Number(new Date().getFullYear()) &&
              yearArray.includes("Brand New")
            ? "01"
            : moment().format("DD-MM-YYYY").split("-")[1]
        }-${
          moment(
            data?.year === "Brand New"
              ? String(new Date().getFullYear())
              : data?.year
          )
            .format("DD-MM-YYYY")
            .split("-")[2]
        }`,
        regNo: temp_data?.regNo,
        leadJourneyEnd: true,
        leadStageId: 2,
      })
    );

    dispatch(
      set_temp_data({
        newCar:
          temp_data?.journeyType === 3 ||
          diffMonthsRollOver === 0 ||
          diffMonthsRollOver < 9,
        leadJourneyEnd: false,
        odOnly: saod(calc_date),
        vehicleInvoiceDate: data?.year && calc_date,
        leadStageId: 2,
      })
    );

    dispatch(
      SaveQuoteData({
        ...(token && { token: token }),
        stage: "9",
        vehicleRegisterDate: data?.year && calc_date,
        vehicleInvoiceDate: data?.year && calc_date,
        manufactureYear:
          data?.year &&
          `${
            data?.year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(data?.year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              data?.year === "Brand New"
                ? String(new Date().getFullYear())
                : data?.year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
        rtoNumber: temp_data?.rtoNumber,
        rto: temp_data?.rtoNumber,
        vehicleRegisterAt: temp_data?.rtoNumber,
        vehicleRegistrationNo: temp_data?.regNo,
        seatingCapacity: temp_data?.seatingCapacity,
        version: temp_data?.versionId,
        versionName: temp_data?.versionName,
        fuelType: temp_data?.fuel,
        vehicleLpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
        model: temp_data?.modelId,
        modelName: temp_data?.modelName,
        manfactureId: temp_data?.manfId,
        manfactureName: temp_data?.manfName,
        enquiryId: enquiry_id,
        userProductJourneyId: enquiry_id,
        policyType: saod(calc_date)
          ? "own_damage"
          : "comprehensive",
        businessType:
          temp_data?.journeyType === 3 ||
          diffMonthsRollOver === 0 ||
          diffMonthsRollOver < 9
            ? "newbusiness"
            : temp_data?.breakIn
            ? "breakin"
            : "rollover",
      })
    );
  };

  // auto fire
  const urlParams = { token, enquiry_id };
  const varParams = { temp_data, saod, year, yearArray, diffMonthsRollOver };

  useAutoTrigger(dispatch, urlParams, varParams);

  const width = lessthan767 ? 99 : 148;
  const height = 40;
  const margin = lessthan767 ? "25px" : undefined;
  const skeletonRows = generateYearSkeletonRows(9, width, height, margin);

  return !stepper1 && !loading ? (
    <Row className="mx-auto d-flex no-wrap mt-4 w-100 ElemFade">
      <Form onSubmit={handleSubmit(onSubmit)} className="w-100 mx-auto">
        {/*DatePicker Btn*/}
        <Col
          xs="12"
          sm="12"
          md="8"
          lg="8"
          xl="8"
          className="w-100 d-flex flex-column align-content-center justify-content-center mx-auto"
        >
          <div className="">
            {/*Date Tiles*/}
            <Row className=" w-100 d-flex justify-content-center mx-auto ElemFade">
              {!_.isEmpty(yearArray) ? (
                yearArray?.map((item) => (
                  <Col
                    xs="4"
                    sm="4"
                    md="4"
                    lg="4"
                    xl="4"
                    className={`d-flex justify-content-center w-100 mx-auto ${
                      lessthan600 ? "px-2 py-0" : ""
                    }`}
                  >
                    <Tile
                      text={item}
                      id={item}
                      register={register}
                      name={"year"}
                      value={item}
                      height={lessthan360 ? "45px" : "40px"}
                      setValue={setValue}
                      width={lessthan600 && "99px"}
                      Selected={
                        year ||
                        (temp_data?.regDate
                          ? Number(temp_data?.regDate.split("-")[2]) ===
                            Number(new Date().getFullYear())
                            ? //check month for brand new/rollover in same year
                              Number(temp_data?.regDate?.split("-")[1]) === 1
                              ? temp_data?.regDate.split("-")[2]
                              : "Brand New"
                            : temp_data?.regDate.split("-")[2]
                          : "")
                      }
                      fontSize={
                        lessthan360 ? "12px" : lessthan600 ? "14px" : ""
                      }
                      fontWeight={lessthan600 && "800"}
                      lessthan600={lessthan600}
                      shadow={lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 10px"}
                    />
                  </Col>
                ))
              ) : (
                <noscript />
              )}
            </Row>
          </div>
        </Col>
      </Form>
    </Row>
  ) : (
    <>
      {lessthan767 ? (
        <>{skeletonRows}</>
      ) : (
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "10px",
          }}
        >
          {skeletonRows}
        </div>
      )}
    </>
  );
};
