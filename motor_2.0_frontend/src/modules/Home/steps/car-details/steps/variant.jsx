/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect } from "react";
import { Tile, MultiSelect, Error, Button as Btn } from "components";
import { Row, Col, Button, Form } from "react-bootstrap";
import { Controller, useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import {
  set_temp_data,
  Variant as VariantType,
  rto,
  SaveQuoteData,
  clear,
} from "modules/Home/home.slice";
import { useSelector, useDispatch } from "react-redux";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { SkeletonRow, SkeletonRowsContainer } from "./skeleton";
import { getVersionLabel } from "./helper";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

// validation schema
const yupValidate = yup.object({
  variant_other: yup.string().required("Variant is required"),
});

export const Variant = ({ stepFn, enquiry_id, type, token, TypeReturn }) => {
  const dispatch = useDispatch();
  const {
    temp_data,
    variant: varntMod,
    loading,
    saveQuoteData,
    stepper1,
  } = useSelector((state) => state.home);

  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const [btnDisable, setbtnDisable] = useState(false);

  const length = !_.isEmpty(varntMod) ? varntMod?.length : 0;
  //filtering out electric vehicles
  const varnt = !_.isEmpty(varntMod) ? varntMod : [];
  const TileVariants = !_.isEmpty(varnt)
    ? length > 12
      ? varnt.slice(0, 12)
      : varnt
    : [];
  const OtherVariants = length > 12 ? varnt : [];

  //Generate version label
  const getVersionLabelOptions = (versionArray) => {
    return versionArray?.map(
      ({
        versionId,
        versionName,
        cubicCapacity,
        grosssVehicleWeight,
        fuelFype,
        kw,
        vehicleBuiltUp,
      }) => {
        let versionLabel = getVersionLabel(
          temp_data,
          TypeReturn(type),
          fuelFype,
          kw,
          cubicCapacity,
          grosssVehicleWeight,
          versionName,
          vehicleBuiltUp
        );

        return {
          label: versionLabel,
          name: versionLabel,
          id: versionId,
          value: versionId,
        };
      }
    );
  };

  const Options = !_.isEmpty(OtherVariants)
    ? getVersionLabelOptions(OtherVariants)
    : [];

  const { handleSubmit, register, watch, control, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });
  const [show, setShow] = useState(false);
  const [showAll, setShowAll] = useState(false);

  //clearing fuel check
  useEffect(() => {
    dispatch(clear("fuelCheck"));
  }, []);

  //load Variant Data
  useEffect(() => {
    if (temp_data?.modelId) {
      dispatch(
        VariantType({
          productSubTypeId: temp_data?.productSubTypeId,
          modelId: temp_data?.modelId,
          fuelType:
            TypeReturn(type) === "bike"
              ? "NULL"
              : temp_data?.fuel
              ? temp_data?.fuel
              : "NULL",
          LpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
          enquiryId: enquiry_id,
        })
      );
    }
  }, [temp_data.modelId]);

  //prefill
  useEffect(() => {
    if (show && temp_data?.versionId && !_.isEmpty(OtherVariants)) {
      let check = OtherVariants?.filter(
        ({ versionId }) =>
          Number(versionId) === Number(temp_data?.versionId) ||
          versionId === temp_data?.versionId
      );
      let selected_option = getVersionLabelOptions(check)
      !_.isEmpty(selected_option) &&
        setValue("variant_other", selected_option[0]);
    }
  }, [show]);

  const other = watch("variant_other");
  const variant = watch("variant");
  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      //Analytics | version Name
      _useMMVTracking("variant", temp_data?.versionName, TypeReturn(type));
      let isRtoRequired =
        Number(temp_data?.journeyType) !== 1 ||
        (Number(temp_data?.journeyType) === 1 &&
          temp_data?.regNo &&
          temp_data?.regNo[0] * 1);
      variant
        ? stepFn(4, isRtoRequired ? 5 : 6)
        : stepFn(4, isRtoRequired ? 5 : 6);
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
  }, [saveQuoteData]);

  useEffect(() => {
    if (variant && !_.isEmpty(TileVariants)) {
      let VariantData = varnt?.filter(
        ({ versionId }) =>
          Number(versionId) === Number(variant) || versionId === variant
      );
      dispatch(
        set_temp_data({
          versionId: VariantData[0]?.versionId,
          versionName: VariantData[0]?.versionName,
          fuelType: VariantData[0]?.fuelFype,
          selectedGvw: VariantData[0]?.grosssVehicleWeight,
          defaultGvw: VariantData[0]?.grosssVehicleWeight,
          ...(VariantData[0]?.fuelFype === "LPG" && {
            fuel: VariantData[0]?.fuelFype,
          }),
          seatingCapacity: VariantData[0]?.seatingCapacity,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          seatingCapacity: VariantData[0]?.seatingCapacity,
          version: VariantData[0]?.versionId,
          versionName: VariantData[0]?.versionName,
          selectedGvw: VariantData[0]?.grosssVehicleWeight
            ? VariantData[0]?.grosssVehicleWeight
            : null,
          defaultGvw: VariantData[0]?.grosssVehicleWeight
            ? VariantData[0]?.grosssVehicleWeight
            : null,
          fuelType: VariantData[0]?.fuelFype,
          vehicleLpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
          model: temp_data?.modelId,
          modelName: temp_data?.modelName,
          manfactureId: temp_data?.manfId,
          manfactureName: temp_data?.manfName,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
          stage: "7",
        })
      );
    }
  }, [variant]);

  const onSubmit = (data) => {
    if ((!_.isEmpty(data) || !_.isEmpty(other)) && !_.isEmpty(OtherVariants)) {
      setbtnDisable(true);
      let VariantData = OtherVariants?.filter(
        ({ versionId }) =>
          Number(versionId) === Number(data?.value || other?.value) ||
          versionId === data?.value ||
          versionId === other?.value
      );
      dispatch(
        set_temp_data({
          versionId: VariantData[0]?.versionId,
          versionName: VariantData[0]?.versionName,
          fuelType: VariantData[0]?.fuelFype,
          selectedGvw: VariantData[0]?.grosssVehicleWeight,
          defaultGvw: VariantData[0]?.grosssVehicleWeight,
          ...(VariantData[0]?.fuelFype === "LPG" && {
            fuel: VariantData[0]?.fuelFype,
          }),
          seatingCapacity: VariantData[0]?.seatingCapacity,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "7",
          seatingCapacity: VariantData[0]?.seatingCapacity,
          version: VariantData[0]?.versionId,
          versionName: VariantData[0]?.versionName,
          fuelType: VariantData[0]?.fuelFype,
          selectedGvw: VariantData[0]?.grosssVehicleWeight
            ? VariantData[0]?.grosssVehicleWeight
            : null,
          defaultGvw: VariantData[0]?.grosssVehicleWeight
            ? VariantData[0]?.grosssVehicleWeight
            : null,
          vehicleLpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
          model: temp_data?.modelId,
          modelName: temp_data?.modelName,
          manfactureId: temp_data?.manfId,
          manfactureName: temp_data?.manfName,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
        })
      );
      //clearing rto
      dispatch(rto([]));
      setTimeout(() => setbtnDisable(false), 2500);
    }
  };

  return (
    <>
      {!loading && !stepper1 ? (
        <>
          {!show ? (
            <>
              <Row className=" w-100 d-flex justify-content-center mx-auto ElemFade">
                {lessthan600 && (
                  <Form
                    onSubmit={handleSubmit(onSubmit)}
                    className="w-100 mx-auto ElemFade mb-3"
                  >
                    <Row
                      className={`mx-auto d-flex no-wrap ${
                        lessthan600 ? "mt-2" : "mt-4"
                      } w-100 text-left`}
                    >
                      <Col xs="12" sm="12" md="12" lg="12" xl="12">
                        <Controller
                          control={control}
                          name="variant_other"
                          render={({ onChange, onBlur, value, name }) => (
                            <MultiSelect
                              name={name}
                              onChange={onChange}
                              ref={register}
                              value={value}
                              onBlur={onBlur}
                              isMulti={false}
                              options={Options}
                              placeholder={"Select Variant"}
                              errors={errors.variant}
                              Styled
                              closeOnSelect
                              onClick={(e) => onSubmit(e)}
                              stepperSelect={lessthan600}
                            />
                          )}
                        />
                      </Col>
                    </Row>
                  </Form>
                )}
                {/* Quickpicker */}
                {!showAll && (
                  <>
                    {!_.isEmpty(varnt) ? (
                      TileVariants?.map(
                        (
                          {
                            versionId,
                            versionName,
                            cubicCapacity,
                            grosssVehicleWeight,
                            kw,
                            fuelFype,
                            vehicleBuiltUp,
                          },
                          index
                        ) => (
                          <Col
                            xs="12"
                            sm="12"
                            md="4"
                            lg="4"
                            xl="3"
                            className={`${
                              !lessthan600 ? "d-flex" : ""
                            } justify-content-center w-100 mx-auto ${
                              lessthan600 ? "px-2 py-0" : ""
                            }`}
                          >
                            <Tile
                              text={getVersionLabel(
                                temp_data,
                                TypeReturn(type),
                                fuelFype,
                                kw,
                                cubicCapacity,
                                grosssVehicleWeight,
                                versionName,
                                vehicleBuiltUp
                              )}
                              id={versionId}
                              register={register}
                              name={"variant"}
                              value={versionId}
                              height={lessthan600 ? "65px" : "82px"}
                              setValue={setValue}
                              Selected={variant || temp_data?.versionId}
                              fontSize={
                                lessthan360 ? "11px" : lessthan600 ? "12px" : ""
                              }
                              fontWeight={lessthan600 && "800"}
                              width={lessthan600 && "100%"}
                              flatTile={lessthan600}
                              shadow={
                                lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 10px"
                              }
                            />
                          </Col>
                        )
                      )
                    ) : (
                      <Col
                        sm="12"
                        md="12"
                        lg="12"
                        xl="12"
                        className="d-flex flex-column justify-content-center align-content-center"
                      >
                        <img
                          src={`${
                            import.meta.env.VITE_BASENAME !== "NA"
                              ? `/${import.meta.env.VITE_BASENAME}`
                              : ""
                          }/assets/images/nodata3.png`}
                          alt="nodata"
                          height="200"
                          width="200"
                          className="mx-auto"
                        />
                        <label
                          className="text-secondary text-center mt-1"
                          style={{ fontSize: "16px" }}
                        >
                          No Data Found
                        </label>
                      </Col>
                    )}
                  </>
                )}
                {/* Quickpicker with all models */}
                {!_.isEmpty(OtherVariants) &&
                  showAll &&
                  lessthan600 &&
                  OtherVariants?.map(
                    (
                      {
                        versionId,
                        versionName,
                        cubicCapacity,
                        grosssVehicleWeight,
                        kw,
                        fuelFype,
                        vehicleBuiltUp,
                      },
                      index
                    ) => (
                      <Col
                        xs="12"
                        sm="12"
                        md="4"
                        lg="4"
                        xl="3"
                        className={`${
                          !lessthan600 ? "d-flex" : ""
                        } justify-content-center w-100 mx-auto ${
                          lessthan600 ? "px-2 py-0" : ""
                        }`}
                      >
                        <Tile
                          text={getVersionLabel(
                            temp_data,
                            TypeReturn(type),
                            fuelFype,
                            kw,
                            cubicCapacity,
                            grosssVehicleWeight,
                            versionName,
                            vehicleBuiltUp
                          )}
                          id={versionId}
                          register={register}
                          name={"variant"}
                          value={versionId}
                          height={lessthan600 ? "65px" : "82px"}
                          setValue={setValue}
                          Selected={variant || temp_data?.versionId}
                          fontSize={
                            lessthan360 ? "11px" : lessthan600 ? "12px" : ""
                          }
                          fontWeight={lessthan600 && "800"}
                          flatTile={lessthan600}
                          shadow={
                            lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 10px"
                          }
                          width={lessthan600 && "100%"}
                        />
                      </Col>
                    )
                  )}
              </Row>
              {!_.isEmpty(OtherVariants) && (
                <Row className="mx-auto d-flex no-wrap mt-4 ElemFade">
                  <Col
                    xs="12"
                    sm="12"
                    md="12"
                    lg="12"
                    xl="12"
                    className="linkLine ElemFade"
                  >
                    <Button
                      variant="link"
                      className={`outline-none ${
                        Theme?.Stepper?.linkColor
                          ? Theme?.Stepper?.linkColor
                          : ""
                      }`}
                      onClick={
                        lessthan600
                          ? () =>
                              !showAll ? setShowAll(true) : setShowAll(false)
                          : () => setShow(true)
                      }
                    >
                      {lessthan600
                        ? showAll
                          ? "Show Popular Variants"
                          : `Show All ${OtherVariants?.length} variants`
                        : "Don't See your vehicle's variant? Click Here"}
                    </Button>
                  </Col>
                </Row>
              )}
            </>
          ) : (
            <Form
              onSubmit={handleSubmit(onSubmit)}
              className="w-100 mx-auto ElemFade"
            >
              <Row className="mx-auto d-flex no-wrap mt-4 w-100 text-left">
                <Col xs="12" sm="12" md="12" lg="12" xl="12">
                  <Controller
                    control={control}
                    name="variant_other"
                    render={({ onChange, onBlur, value, name }) => (
                      <MultiSelect
                        name={name}
                        onChange={onChange}
                        ref={register}
                        value={value}
                        onBlur={onBlur}
                        isMulti={false}
                        options={Options}
                        placeholder={"Select Variant"}
                        errors={errors.variant}
                        Styled
                        closeOnSelect
                        onClick={(e) => onSubmit(e)}
                      />
                    )}
                  />
                  {!!errors?.variant && (
                    <Error className="mt-1">{errors?.variant?.message}</Error>
                  )}
                </Col>
              </Row>
              <Row>
                <Col
                  sm="12"
                  md="12"
                  lg="12"
                  xl="12"
                  className="d-flex justify-content-center mt-5"
                >
                  <Btn
                    disabled={btnDisable}
                    onClick={() => {
                      if (!_.isEmpty(other)) {
                        onSubmit(other);
                        // setbtnDisable(true);
                      } else {
                        handleSubmit(onSubmit);
                      }
                    }}
                    buttonStyle="outline-solid"
                    hex1={Theme?.Registration?.otherBtn?.hex1 || "#006400"}
                    hex2={Theme?.Registration?.otherBtn?.hex2 || "#228B22"}
                    borderRadius="5px"
                    shadow={"none"}
                  >
                    Proceed
                  </Btn>
                </Col>
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
                      Theme?.Stepper?.linkColor ? Theme?.Stepper?.linkColor : ""
                    }`}
                    onClick={() => setShow(false)}
                  >
                    {"Go back to the Quick Picker"}
                  </Button>
                </Col>
              </Row>
            </Form>
          )}
        </>
      ) : (
        <>
          {lessthan767 ? (
            <>
              <div style={{ margin: "25px 0" }}>
                <SkeletonRow count={1} height={60} />
              </div>
              <SkeletonRowsContainer count={12} height={75} />
            </>
          ) : (
            <>
              <SkeletonRow count={4} height={82} />
              <SkeletonRow count={4} height={82} />
              <SkeletonRow count={4} height={82} />
            </>
          )}
        </>
      )}
    </>
  );
};
