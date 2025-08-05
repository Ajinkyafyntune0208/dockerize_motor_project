/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect } from "react";
import { MultiSelect, Button as Btn, Error, Delay } from "components";
import { Row, Col, Button, Form } from "react-bootstrap";
import { Controller, useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import { useDispatch, useSelector } from "react-redux";
import {
  Rto,
  set_temp_data,
  SaveQuoteData,
  clear,
} from "modules/Home/home.slice";
import moment from "moment";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { BtnDiv, BtnDiv2 } from "../style";
import { SkeletonRow, SkeletonRowsContainer } from "./skeleton";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";
import { TypeReturn } from "modules/type";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

// validation schema
const yupValidate = yup.object({
  sub_no: yup.string().required("RTO is required"),
});

export const City = ({ stepFn, enquiry_id, isMobileIOS, token, type }) => {
  const dispatch = useDispatch();
  const {
    rto,
    temp_data,
    loading,
    saveQuoteData,
    stepper1,
    rtoCities,
    rtoCitiesInfo,
  } = useSelector((state) => state.home);
  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan400 = useMediaPredicate("(max-width: 400px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");

  const [btnDisable, setbtnDisable] = useState(false);

  //selection of selector tiles
  const [parent, setParent] = useState(false);
  const [child, setChild] = useState(false);

  const RtoData = !_.isEmpty(rto)
    ? rto?.map(({ rtoNumber, rtoName, rtoId, stateName }) => {
        let rtoNewNumber = rtoNumber.split("-").join("");
        return {
          rtoNumber,
          rtoId,
          rtoName,
          stateName,
          label: `${rtoNewNumber} (${stateName} : ${rtoName})`,
          name: `${rtoNumber?.replace(/-/g, "")} - (${stateName} : ${rtoName})`,
          value: rtoId,
          id: rtoId,
        };
      })
    : [];

  const { handleSubmit, register, watch, control, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  //get rto
  useEffect(() => {
    dispatch(Rto({ enquiry_id }));
  }, []);

  //switcher state
  const [showdd, setShowdd] = useState(lessthan600 ? true : false);

  //prefill
  useEffect(() => {
    if (temp_data?.rtoNumber && !loading) {
      const filtered_data = !_.isEmpty(rto)
        ? rto?.filter(({ rtoNumber }, index) => {
            return rtoNumber === temp_data?.rtoNumber;
          })
        : [];
      let selected_option = [
        {
          rtoNumber: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoNumber,
          rtoId: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
          stateName: !_.isEmpty(filtered_data) && filtered_data[0]?.stateName,
          rtoName: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoName,
          label:
            !_.isEmpty(filtered_data) &&
            `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
              filtered_data[0]?.stateName
            } : ${filtered_data[0]?.rtoName})`,
          name:
            !_.isEmpty(filtered_data) &&
            `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
              filtered_data[0]?.stateName
            } : ${filtered_data[0]?.rtoName})`,
          value: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
          id: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
        },
      ];

      // parent tile and child tile
      let parentTile = !_.isEmpty(selected_option) && selected_option[0];
      !_.isEmpty(parentTile) &&
        parentTile &&
        selected_option[0]?.rtoName &&
        setParent(selected_option[0]?.rtoName?.toLowerCase());
      temp_data?.rtoNumber && setChild(temp_data?.rtoNumber);
      //prefilling Drop Down
      !_.isEmpty(selected_option) && setValue("sub_no", selected_option[0]);
    }
  }, [temp_data, loading, rto, showdd]);

  //onClick Eval for tiles
  const OnTileClick = (selectedRtoNumber) => {
    const filtered_data = !_.isEmpty(rto)
      ? rto?.filter(({ rtoNumber }, index) => {
          return rtoNumber === selectedRtoNumber;
        })
      : [];
    let selected_option = [
      {
        rtoNumber: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoNumber,
        rtoId: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
        stateName: !_.isEmpty(filtered_data) && filtered_data[0]?.stateName,
        rtoName: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoName,
        label:
          !_.isEmpty(filtered_data) &&
          `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
            filtered_data[0]?.stateName
          } : ${filtered_data[0]?.rtoName})`,
        name:
          !_.isEmpty(filtered_data) &&
          `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
            filtered_data[0]?.stateName
          } : ${filtered_data[0]?.rtoName})`,
        value: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
        id: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
      },
    ];
    return !_.isEmpty(selected_option) && selected_option[0];
  };

  const sub_no = watch("sub_no");

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      //Analytics | RTO Name
      _useMMVTracking("rto", temp_data?.rtoNumber, TypeReturn(type));
      stepFn(5, 6);
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
  }, [saveQuoteData]);

  const onSubmit = (data) => {
    if (!_.isEmpty(data)) {
      setbtnDisable(true);
      dispatch(
        set_temp_data({
          rtoNumber: data?.rtoNumber || sub_no?.rtoNumber,
          rtoId: data?.rtoId || sub_no?.rtoId,
          stateName: data?.stateName || sub_no?.stateName,
          rto: data?.rtoNumber || sub_no?.rtoNumber,
          vehicleRegisterAt: data?.rtoNumber || sub_no?.rtoNumber,
          rtoName: data?.rtoName || sub_no?.rtoName,
          ...((Number(temp_data?.journeyType) === 3 ||
            temp_data?.regNo === "NEW") && {
            regDate: `${moment().format("DD-MM-YYYY").split("-")[0]}-${
              moment().format("DD-MM-YYYY").split("-")[1]
            }-${moment().format("DD-MM-YYYY").split("-")[2]}`,
            vehicleInvoiceDate: `${
              moment().format("DD-MM-YYYY").split("-")[0]
            }-${moment().format("DD-MM-YYYY").split("-")[1]}-${
              moment().format("DD-MM-YYYY").split("-")[2]
            }`,
            manfDate: `${moment().format("DD-MM-YYYY").split("-")[1]}-${
              moment().format("DD-MM-YYYY").split("-")[2]
            }`,
          }),
        })
      );
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "8",
          rtoNumber: data?.rtoNumber || sub_no?.rtoNumber,
          rto: data?.rtoNumber || sub_no?.rtoNumber,
          vehicleRegisterAt: data?.rtoNumber || sub_no?.rtoNumber,
          seatingCapacity: temp_data?.seatingCapacity,
          version: temp_data?.versionId,
          versionName: temp_data?.versionName,
          fuelType: temp_data?.fuel,
          vehicleLpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
          model: temp_data?.modelId,
          modelName: temp_data?.modelName,
          manfactureId: temp_data?.manfId,
          manfactureName: temp_data?.manfName,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
          ...((Number(temp_data?.journeyType) === 3 ||
            temp_data?.regNo === "NEW") && {
            vehicleRegisterDate: `${
              moment().format("DD-MM-YYYY").split("-")[0]
            }-${moment().format("DD-MM-YYYY").split("-")[1]}-${
              moment().format("DD-MM-YYYY").split("-")[2]
            }`,
            vehicleInvoiceDate: `${
              moment().format("DD-MM-YYYY").split("-")[0]
            }-${moment().format("DD-MM-YYYY").split("-")[1]}-${
              moment().format("DD-MM-YYYY").split("-")[2]
            }`,
            manufactureYear: `${moment().format("DD-MM-YYYY").split("-")[1]}-${
              moment().format("DD-MM-YYYY").split("-")[2]
            }`,
          }),
        })
      );
      setTimeout(() => setbtnDisable(false), 3500);
    }
  };

  //auto click in IOS
  useEffect(() => {
    if (!_.isEmpty(RtoData) && isMobileIOS && !loading) {
      // return document?.getElementById("rtoDD").click() ;
    }
  }, [loading, RtoData, isMobileIOS]);

  return (
    <>
      {!loading && !stepper1 ? (
        <Row
          className={`mx-auto d-flex no-wrap ${
            lessthan600 ? "mt-2" : showdd ? "mt-4" : "mt-0"
          } w-100 ElemFade`}
        >
          {
            <Form
              onSubmit={handleSubmit(onSubmit)}
              className="w-100 mx-auto text-center"
            >
              {showdd && (
                <>
                  <Col
                    xs="12"
                    sm="12"
                    md="10"
                    lg="10"
                    xl="10"
                    className="w-100 mx-auto text-left ElemFade text-left"
                  >
                    <Controller
                      control={control}
                      name="sub_no"
                      render={({ onChange, onBlur, value, name }) => (
                        <MultiSelect
                          borderRadius="10px"
                          id={"rtoDD"}
                          autoFocus={!lessthan600}
                          defaultMenuIsOpen={!lessthan600}
                          name={name}
                          onChange={onChange}
                          ref={register}
                          value={value}
                          onBlur={onBlur}
                          onClick={onSubmit}
                          isMulti={false}
                          options={RtoData}
                          errors={errors.sub_no}
                          placeholder={"Select"}
                          Styled
                          closeOnSelect={true}
                          customSearch
                          noBorder
                          rto
                          stepperSelect={lessthan600}
                        />
                      )}
                    />
                    {!!errors?.sub_no && (
                      <Error className="mt-1">{errors?.sub_no?.message}</Error>
                    )}
                  </Col>
                  <Delay>
                    {!lessthan600 && (
                      <Col
                        xs="12"
                        sm="12"
                        md="12"
                        lg="12"
                        xl="12"
                        className="mx-auto d-flex no-wrap mt-3 text-center linkLine w-100 d-flex justify-content-center"
                      >
                        <Button
                          variant="link"
                          className={`outline-none ${
                            Theme?.Stepper?.linkColor
                              ? Theme?.Stepper?.linkColor
                              : ""
                          }`}
                          onClick={() => setShowdd(false)}
                        >
                          {"Go back to the Quick Picker"}
                        </Button>
                      </Col>
                    )}
                  </Delay>
                </>
              )}

              {/*---- Desktop View ----*/}
              {!lessthan600 && !showdd && (
                <>
                  <Row className="w-100 mx-auto text-center">
                    {/* Parent tiles*/}
                    {!_.isEmpty(rtoCities) &&
                      rtoCities &&
                      !_.isEmpty(rtoCitiesInfo) &&
                      rtoCitiesInfo &&
                      rtoCities.map((cityItem) => (
                        <>
                          <Col
                            xs="12"
                            sm="12"
                            md="6"
                            lg="6"
                            xl="6"
                            className="w-100 mx-auto text-center px-1"
                          >
                            <div className="w-100 mx-auto text-center my-1">
                              <BtnDiv>
                                <Button
                                  style={{ transition: "0.2s ease-in-out" }}
                                  onClick={(e) => [
                                    parent !== cityItem
                                      ? setParent(cityItem)
                                      : setParent(false),
                                  ]}
                                  variant={
                                    parent === cityItem
                                      ? Theme?.journeyType?.buttonVariant ||
                                        Theme?.buttonVariantScheme?.[0]
                                        ? Theme?.journeyType?.buttonVariant ||
                                          Theme?.buttonVariantScheme?.[0]
                                        : "success"
                                      : Theme?.journeyType?.outlineVariant ||
                                        Theme?.outlineButtonVariantScheme?.[0]
                                      ? Theme?.journeyType?.outlineVariant ||
                                        Theme?.outlineButtonVariantScheme?.[0]
                                      : "outline-success"
                                  }
                                  className="text-center w-100"
                                >
                                  <text className="text-center font-weight-bold">
                                    {_.capitalize(cityItem)}
                                  </text>
                                  <i
                                    style={{
                                      fontSize: "18px",
                                      position: "relative",
                                      top: "2.2px",
                                      float: "right",
                                      fontWeight: 600,
                                    }}
                                    className={
                                      parent === cityItem
                                        ? "ml-1 fa fa-angle-up"
                                        : "ml-1 fa fa-angle-down"
                                    }
                                  ></i>
                                </Button>
                              </BtnDiv>
                            </div>
                            {/* child tiles*/}
                            {parent === cityItem && (
                              <div className="w-100 text-center mt-2 d-flex justify-content-center flex-wrap ElemFade p-0">
                                {rtoCitiesInfo?.[
                                  `${cityItem}`?.replace(/ /g, "")
                                ] &&
                                  rtoCitiesInfo?.[
                                    `${cityItem}`?.replace(/ /g, "")
                                  ].map((rtoItem) => (
                                    <BtnDiv2>
                                      <Button
                                        size="sm"
                                        variant={
                                          rtoItem?.rtoNumber ===
                                          temp_data?.rtoNumber
                                            ? Theme?.journeyType
                                                ?.buttonVariant ||
                                              Theme?.buttonVariantScheme?.[0]
                                              ? Theme?.journeyType
                                                  ?.buttonVariant ||
                                                Theme?.buttonVariantScheme?.[0]
                                              : "success"
                                            : Theme?.journeyType
                                                ?.outlineVariant ||
                                              Theme
                                                ?.outlineButtonVariantScheme?.[0]
                                            ? Theme?.journeyType
                                                ?.outlineVariant ||
                                              Theme
                                                ?.outlineButtonVariantScheme?.[0]
                                            : "outline-success"
                                        }
                                        className="m-1"
                                        style={{
                                          width: "76px",
                                          fontWeight: 600,
                                        }}
                                        onClick={() =>
                                          OnTileClick(rtoItem?.rtoNumber)
                                            ? onSubmit(
                                                OnTileClick(rtoItem?.rtoNumber)
                                              )
                                            : {}
                                        }
                                      >
                                        <text
                                          style={
                                            child !== rtoItem?.rtoNumber
                                              ? {
                                                  fontWeight: 600,
                                                  fontSize: lessthan360
                                                    ? "13px"
                                                    : lessthan400
                                                    ? "13.5px"
                                                    : "14px",
                                                }
                                              : {
                                                  fontWeight: 600,
                                                  fontSize: lessthan360
                                                    ? "13px"
                                                    : lessthan400
                                                    ? "13.5px"
                                                    : "14px",
                                                }
                                          }
                                          className={`mx-auto`}
                                        >
                                          {rtoItem?.rtoNumber || "N/A"}
                                        </text>
                                      </Button>
                                    </BtnDiv2>
                                  ))}
                              </div>
                            )}
                          </Col>
                        </>
                      ))}
                  </Row>
                  <Row className="mx-auto d-flex no-wrap mt-3 text-center">
                    <Col
                      xs="12"
                      sm="12"
                      md="12"
                      lg="12"
                      xl="12"
                      className="linkLine"
                    >
                      <Button
                        variant="link"
                        className={`outline-none ${
                          Theme?.Stepper?.linkColor
                            ? Theme?.Stepper?.linkColor
                            : ""
                        }`}
                        onClick={() => setShowdd(true)}
                      >
                        {"Don't See your Vehicle's RTO? Click Here"}
                      </Button>
                    </Col>
                  </Row>
                </>
              )}
              {/*---- Desktop View ----*/}
              {/*---- Mobile View ----*/}
              {lessthan600 && (
                <Col
                  xs="12"
                  sm="12"
                  md="10"
                  lg="10"
                  xl="10"
                  className="w-100 mx-auto text-center mt-4"
                >
                  {/* Parent tiles*/}
                  {!_.isEmpty(rtoCities) &&
                    rtoCities &&
                    !_.isEmpty(rtoCitiesInfo) &&
                    rtoCitiesInfo &&
                    rtoCities.map((cityItem) => (
                      <>
                        <div className="w-100 mx-auto text-center my-3 capstonesel">
                          <BtnDiv>
                            <Button
                              onClick={(e) => [
                                parent !== cityItem
                                  ? setParent(cityItem)
                                  : setParent(false),
                              ]}
                              variant={
                                parent === cityItem
                                  ? Theme?.journeyType?.buttonVariant ||
                                    Theme?.buttonVariantScheme?.[0]
                                    ? Theme?.journeyType?.buttonVariant ||
                                      Theme?.buttonVariantScheme?.[0]
                                    : "success"
                                  : Theme?.journeyType?.outlineVariant ||
                                    Theme?.outlineButtonVariantScheme?.[0]
                                  ? Theme?.journeyType?.outlineVariant ||
                                    Theme?.outlineButtonVariantScheme?.[0]
                                  : "outline-success"
                              }
                              className="text-center w-100"
                            >
                              <text className="text-center">
                                {_.capitalize(cityItem)}
                              </text>
                              <i
                                style={{
                                  fontSize: "18px",
                                  position: "relative",
                                  top: "2.2px",
                                  float: "right",
                                  fontWeight: 600,
                                }}
                                className={
                                  parent === cityItem
                                    ? "ml-1 fa fa-angle-up"
                                    : "ml-1 fa fa-angle-down"
                                }
                              ></i>
                            </Button>
                          </BtnDiv>
                        </div>
                        {/* child tiles*/}
                        {parent === cityItem && (
                          <div className="w-100 text-center mt-2 d-flex justify-content-center flex-wrap">
                            {rtoCitiesInfo?.[`${cityItem}`] &&
                              rtoCitiesInfo?.[`${cityItem}`].map((rtoItem) => (
                                <BtnDiv2>
                                  <Button
                                    size="sm"
                                    variant={
                                      Theme?.journeyType?.outlineVariant ||
                                      Theme?.outlineButtonVariantScheme?.[0]
                                        ? Theme?.journeyType?.outlineVariant ||
                                          Theme?.outlineButtonVariantScheme?.[0]
                                        : "outline-success"
                                    }
                                    className="m-1"
                                    style={{
                                      width: lessthan360
                                        ? "70px"
                                        : lessthan400 && isMobileIOS
                                        ? "70px"
                                        : "68px",
                                    }}
                                    onClick={() =>
                                      OnTileClick(rtoItem?.rtoNumber)
                                        ? onSubmit(
                                            OnTileClick(rtoItem?.rtoNumber)
                                          )
                                        : {}
                                    }
                                  >
                                    <text
                                      style={
                                        child !== rtoItem?.rtoNumber
                                          ? {
                                              color: "#81919d",
                                              fontSize: lessthan360
                                                ? "13px"
                                                : lessthan400
                                                ? "13.5px"
                                                : "14px",
                                            }
                                          : {
                                              fontSize: lessthan360
                                                ? "13px"
                                                : lessthan400
                                                ? "13.5px"
                                                : "14px",
                                            }
                                      }
                                      className={`mx-auto`}
                                    >
                                      {rtoItem?.rtoNumber || "N/A"}
                                    </text>
                                  </Button>
                                </BtnDiv2>
                              ))}
                          </div>
                        )}
                      </>
                    ))}
                </Col>
              )}
              {temp_data?.rtoNumber && showdd && (
                <Delay>
                  <Col
                    sm="12"
                    md="12"
                    lg="12"
                    xl="12"
                    className={`d-flex justify-content-center ${
                      lessthan600 ? "w-100 mt-4" : "mt-5"
                    }`}
                  >
                    <Btn
                      className={lessthan600 ? "w-100" : ""}
                      disabled={btnDisable}
                      onClick={() => {
                        if (!_.isEmpty(sub_no)) {
                          onSubmit(sub_no);
                        }
                      }}
                      buttonStyle="outline-solid"
                      hex1={Theme?.Registration?.otherBtn?.hex1 || "#006400"}
                      hex2={Theme?.Registration?.otherBtn?.hex2 || "#228B22"}
                      borderRadius="5px"
                      type="submit"
                      shadow={"none"}
                    >
                      Proceed
                    </Btn>
                  </Col>
                </Delay>
              )}
            </Form>
          }
        </Row>
      ) : (
        <>
          {lessthan767 ? (
            <>
              <div style={{ margin: "25px 0" }}>
                <SkeletonRow count={1} height={60} />
              </div>
              <SkeletonRowsContainer count={10} height={50} />
            </>
          ) : (
            <>
              <SkeletonRow count={2} height={50} />
              <SkeletonRow count={2} height={50} />
              <SkeletonRow count={2} height={50} />
              <SkeletonRow count={2} height={50} />
              <SkeletonRow count={2} height={50} />
            </>
          )}
        </>
      )}
    </>
  );
};
