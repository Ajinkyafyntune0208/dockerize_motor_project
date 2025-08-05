import React, { useState, useEffect, useMemo } from "react";
import { Tile, MultiSelect, Error, Button as Btn } from "components";
import { Row, Col, Button, Form } from "react-bootstrap";
import { Controller, useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import {
  ModelType,
  set_temp_data,
  SaveQuoteData,
  clear,
  FuelTypeCheck,
} from "modules/Home/home.slice";
import { useSelector, useDispatch } from "react-redux";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { SkeletonRow } from "./skeleton";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

// validation schema
const yupValidate = yup.object({
  model_other: yup.string().required("Model is required"),
});

export const Model = ({ stepFn, enquiry_id, type, token, TypeReturn }) => {
  const dispatch = useDispatch();
  const {
    modelType,
    temp_data,
    loading,
    saveQuoteData,
    fuelCheck,
    stepper2,
    stepper3,
  } = useSelector((state) => state.home);

  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const [btnDisable, setbtnDisable] = useState(false);

  const length = !_.isEmpty(modelType) ? modelType?.length : 0;
  const TileModels = !_.isEmpty(modelType)
    ? length > 12
      ? modelType.slice(0, 12)
      : modelType
    : [];
  const OtherModels = length > 12 ? modelType : [];
  const Options = !_.isEmpty(OtherModels)
    ? OtherModels?.map(({ modelName, modelId }) => ({
        label: modelName,
        name: modelName,
        id: modelId,
        value: modelId,
      }))
    : [];

  const { handleSubmit, register, watch, control, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });
  const [show, setShow] = useState(false);
  const [showAll, setShowAll] = useState(false);

  //load Model Data
  useMemo(() => {
    if (temp_data?.productSubTypeId && temp_data?.manfId) {
      dispatch(
        ModelType(
          {
            productSubTypeId: temp_data?.productSubTypeId,
            manfId: temp_data?.manfId,
            enquiryId: enquiry_id,
          },
          true
        )
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.manfId]);

  useEffect(() => {
    if (show && temp_data?.modelId && !_.isEmpty(OtherModels)) {
      let check = OtherModels?.filter(
        ({ modelId }) => Number(modelId) === Number(temp_data?.modelId)
      );
      let selected_option = check?.map(({ modelId, modelName }) => {
        return {
          id: modelId,
          value: modelId,
          label: modelName,
          name: modelName,
        };
      });
      !_.isEmpty(selected_option) &&
        setValue("model_other", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [show]);

  const other = watch("model_other");
  const model = watch("model");

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      //Analytics | Brand Name
      _useMMVTracking("model", temp_data?.modelName, TypeReturn(type));
      dispatch(clear("saveQuoteData"));
      if (
        temp_data?.productSubTypeId &&
        temp_data?.modelId &&
        TypeReturn(type) !== "bike"
      ) {
        dispatch(
          FuelTypeCheck({
            modelId: temp_data?.modelId,
            productSubTypeId: temp_data?.productSubTypeId,
            enquiryId: enquiry_id,
          })
        );
      } else {
        model
          ? stepFn(
              TypeReturn(type) === "bike" ? 3 : 2,
              TypeReturn(type) === "bike" ? 4 : 3
            )
          : stepFn(
              TypeReturn(type) === "bike" ? 3 : 2,
              TypeReturn(type) === "bike" ? 4 : 3
            );
      }
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteData]);

  //OnSuccess of fuel check
  useEffect(() => {
    if (!_.isEmpty(fuelCheck)) {
      //clearing previous fuel TypeReturn(type) if only one is present
      fuelCheck.length * 1 === 1 && dispatch(set_temp_data({ fuel: null }));
      dispatch(clear("fuelCheck"));
      model
        ? stepFn(
            TypeReturn(type) === "bike"
              ? 3
              : fuelCheck.length * 1 === 1
              ? 3
              : 2,
            TypeReturn(type) === "bike" ? 4 : fuelCheck.length * 1 === 1 ? 4 : 3
          )
        : stepFn(
            TypeReturn(type) === "bike"
              ? 3
              : fuelCheck.length * 1 === 1
              ? 3
              : 2,
            TypeReturn(type) === "bike" ? 4 : fuelCheck.length * 1 === 1 ? 4 : 3
          );
    }

    return () => {
      !_.isEmpty(fuelCheck) && dispatch(clear("fuelCheck"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fuelCheck]);

  useEffect(() => {
    if (model && !_.isEmpty(modelType)) {
      let ModelData = modelType?.filter(
        ({ modelId }) => Number(modelId) === Number(model)
      );
      dispatch(
        set_temp_data({
          modelId: ModelData[0]?.modelId,
          modelName: ModelData[0]?.modelName,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      dispatch(
        SaveQuoteData(
          {
            ...(token && { token: token }),
            stage: "5",
            model: ModelData[0]?.modelId,
            modelName: ModelData[0]?.modelName,
            manfactureId: temp_data?.manfId,
            manfactureName: temp_data?.manfName,
            userProductJourneyId: enquiry_id,
            enquiryId: enquiry_id,
          },
          true
        )
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [model]);

  useEffect(() => {
    dispatch(clear("saveQuoteData"));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = (data) => {
    if ((!_.isEmpty(data) || !_.isEmpty(other)) && !_.isEmpty(modelType)) {
      setbtnDisable(true);
      let ModelData = modelType?.filter(
        ({ modelId }) => Number(modelId) === Number(data?.value || other?.value)
      );
      dispatch(
        set_temp_data({
          modelId: ModelData[0]?.modelId,
          modelName: ModelData[0]?.modelName,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );

      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "5",
          model: ModelData[0]?.modelId,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
          modelName: ModelData[0]?.modelName,
          manfactureId: temp_data?.manfId,
          manfactureName: temp_data?.manfName,
        })
      );
      setTimeout(() => setbtnDisable(false), 2500);
    }
  };

  return (
    <>
      {!loading && !stepper2 && !stepper3 ? (
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
                          name="model_other"
                          render={({ onChange, onBlur, value, name }) => (
                            <MultiSelect
                              name={name}
                              onChange={onChange}
                              ref={register}
                              value={value}
                              onBlur={onBlur}
                              isMulti={false}
                              options={Options}
                              placeholder={"Select Model"}
                              errors={errors.model}
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
                    {!_.isEmpty(modelType) ? (
                      TileModels?.map(({ modelId, modelName }, index) => (
                        <Col
                          xs="4"
                          sm="4"
                          md="4"
                          lg="4"
                          xl="3"
                          className={`d-flex justify-content-center w-100 mx-auto ${
                            lessthan600 ? "px-2 py-0" : ""
                          }`}
                        >
                          <Tile
                            text={modelName || "N/A"}
                            id={modelId}
                            register={register}
                            name={"model"}
                            value={modelId}
                            height={lessthan600 ? "55px" : "50px"}
                            setValue={setValue}
                            Selected={model || temp_data?.modelId}
                            fontSize={
                              lessthan360 ? "11px" : lessthan600 ? "12px" : ""
                            }
                            fontWeight={lessthan600 && "800"}
                            shadow={
                              lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 20px"
                            }
                          />
                        </Col>
                      ))
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
                {!_.isEmpty(OtherModels) &&
                  showAll &&
                  lessthan600 &&
                  OtherModels?.map(({ modelId, modelName }, index) => (
                    <Col
                      xs="4"
                      sm="4"
                      md="4"
                      lg="4"
                      xl="3"
                      className={`d-flex justify-content-center w-100 mx-auto ${
                        lessthan600 ? "px-2 py-0" : ""
                      }`}
                    >
                      <Tile
                        text={modelName || "N/A"}
                        id={modelId}
                        register={register}
                        name={"model"}
                        value={modelId}
                        height={lessthan600 ? "55px" : "50px"}
                        setValue={setValue}
                        Selected={model || temp_data?.modelId}
                        fontSize={
                          lessthan360 ? "11px" : lessthan600 ? "12px" : ""
                        }
                        fontWeight={lessthan600 && "800"}
                        shadow={lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 20px"}
                      />
                    </Col>
                  ))}
              </Row>
              {!_.isEmpty(OtherModels) && (
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
                          ? "Show Popular Models"
                          : `Show All ${OtherModels?.length} Models`
                        : "Don't See your Vehicle's Model? Click Here"}
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
                    name="model_other"
                    render={({ onChange, onBlur, value, name }) => (
                      <MultiSelect
                        name={name}
                        onChange={onChange}
                        ref={register}
                        value={value}
                        onBlur={onBlur}
                        isMulti={false}
                        options={Options}
                        placeholder={"Select Model"}
                        errors={errors.model}
                        Styled
                        closeOnSelect
                        onClick={(e) => onSubmit(e)}
                      />
                    )}
                  />
                  {!!errors?.model && (
                    <Error className="mt-1">{errors?.model?.message}</Error>
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
              <SkeletonRow count={1} height={60} />
              <SkeletonRow margin={"15px"} count={3} height={55} />
              <SkeletonRow count={3} height={55} />
              <SkeletonRow count={3} height={55} />
              <SkeletonRow count={3} height={55} />
            </>
          ) : (
            <>
              <SkeletonRow count={4} height={50} />
              <SkeletonRow count={4} height={50} />
              <SkeletonRow count={4} height={50} />
            </>
          )}
        </>
      )}
    </>
  );
};
