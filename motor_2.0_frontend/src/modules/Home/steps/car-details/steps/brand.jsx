/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect } from "react";
import { Tile, MultiSelect, Error, Button as Btn } from "components";
import { Row, Col, Button, Form } from "react-bootstrap";
import { Controller, useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import { useSelector, useDispatch } from "react-redux";
import {
  BrandType,
  set_temp_data,
  modelType,
  SaveQuoteData,
  clear,
} from "modules/Home/home.slice";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { SkeletonRow } from "./skeleton";
import { TypeReturn } from "modules/type";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

// validation schema
const yupValidate = yup.object({
  brand_other: yup.string().required("Brand is required"),
});

export const Brand = ({ stepFn, enquiry_id, token, type }) => {
  const dispatch = useDispatch();
  const { brandType, temp_data, loading, saveQuoteData, stepper1 } =
    useSelector((state) => state.home);

  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const [btnDisable, setbtnDisable] = useState(false);

  const length = !_.isEmpty(brandType) ? brandType?.length : 0;
  const TileBrands = !_.isEmpty(brandType)
    ? length > 12
      ? brandType.slice(0, 12)
      : brandType
    : [];
  const OtherBrands = length > 12 ? brandType : [];
  const Options = !_.isEmpty(OtherBrands)
    ? OtherBrands?.map(({ manfName, manfId }) => ({
        label: manfName,
        name: manfName,
        id: manfId,
        value: manfId,
      }))
    : [];

  const { handleSubmit, register, watch, control, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });
  const [show, setShow] = useState(false);
  const [showAll, setShowAll] = useState(false);

  //load Brand Data
  useEffect(() => {
    if (temp_data?.productSubTypeId) {
      dispatch(
        BrandType({
          productSubTypeId: temp_data?.productSubTypeId,
          enquiryId: enquiry_id,
        })
      );
    }
  }, [temp_data.productSubTypeId]);

  useEffect(() => {
    if (show && temp_data?.manfId && !_.isEmpty(OtherBrands)) {
      let check = OtherBrands?.filter(
        ({ manfId }) => Number(manfId) === Number(temp_data?.manfId)
      );
      let selected_option = check?.map(({ manfId, manfName }) => {
        return { id: manfId, value: manfId, label: manfName, name: manfName };
      });
      !_.isEmpty(selected_option) &&
        setValue("brand_other", selected_option[0]);
    }
  }, [show]);

  const brand = watch("brand");
  const other = watch("brand_other");

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      //Analytics | Brand Name
      _useMMVTracking("brand", temp_data?.manfName, TypeReturn(type));
      brand ? stepFn(1, 2) : stepFn(1, 2);
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
  }, [saveQuoteData]);

  useEffect(() => {
    if (brand && !_.isEmpty(TileBrands)) {
      let BrandData = [...TileBrands, ...OtherBrands]?.filter(
        ({ manfId }) => Number(manfId) === Number(brand)
      );
      dispatch(
        set_temp_data({
          manfId: BrandData[0]?.manfId,
          manfName: BrandData[0]?.manfName,
          manfactureName: BrandData[0]?.manfName, 
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "4",
          manfactureId: BrandData[0]?.manfId,
          manfactureName: BrandData[0]?.manfName,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
        })
      );
      //clearing model type
      dispatch(modelType([]));
    }
  }, [brand]);

  const onSubmit = (data) => {
    if ((!_.isEmpty(data) || !_.isEmpty(other)) && !_.isEmpty(OtherBrands)) {
      setbtnDisable(true);
      let BrandData = OtherBrands?.filter(
        ({ manfId }) => Number(manfId) === Number(data?.value || other?.value)
      );
      dispatch(
        set_temp_data({
          manfId: BrandData[0]?.manfId,
          manfName: BrandData[0]?.manfName,
          manfactureName: BrandData[0]?.manfName,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "4",
          manfactureId: BrandData[0]?.manfId,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
          manfactureName: BrandData[0]?.manfName,
        })
      );
      setTimeout(() => setbtnDisable(false), 2500);
    }
  };

  return (
    <>
      {!loading && !stepper1 ? (
        <>
          {!show ? (
            <>
              <Row className="w-100 d-flex justify-content-center mx-auto ElemFade">
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
                          name="brand_other"
                          render={({ onChange, onBlur, value, name }) => (
                            <MultiSelect
                              name={name}
                              value={value}
                              onChange={onChange}
                              ref={register}
                              onBlur={onBlur}
                              isMulti={false}
                              options={Options}
                              placeholder={"Select Brand"}
                              errors={errors.brand_other}
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
                    {!_.isEmpty(brandType) ? (
                      (!showAll ? TileBrands : OtherBrands)?.map(
                        ({ img, manfId, manfName }, index) => (
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
                              logo={img}
                              text={manfName || "N/A"}
                              id={manfId}
                              register={register}
                              name={"brand"}
                              value={manfId}
                              setValue={setValue}
                              Selected={brand || temp_data?.manfId}
                              marginImg={" 2.5px auto 2.5px auto"}
                              Imgheight={lessthan600 && "45.75px"}
                              ImgWidth={lessthan600 && "72.5%"}
                              height={
                                lessthan360
                                  ? "75px"
                                  : lessthan600
                                  ? "94px"
                                  : "88px"
                              }
                              width={
                                lessthan360
                                  ? "124px"
                                  : lessthan600
                                  ? "124px"
                                  : ""
                              }
                              fontSize={
                                lessthan360
                                  ? "9.5px"
                                  : lessthan600
                                  ? "10px"
                                  : ""
                              }
                              lessthan600={lessthan600}
                              fontWeight={lessthan600 && "800"}
                              lessthan360={lessthan360}
                              shadow={
                                lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 20px"
                              }
                              objectFit
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
                {/* Quickpicker with all brands */}
                {!_.isEmpty(OtherBrands) &&
                  showAll &&
                  lessthan600 &&
                  OtherBrands?.map(({ img, manfId, manfName }, index) => (
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
                        logo={img}
                        text={manfName || "N/A"}
                        id={manfId}
                        register={register}
                        name={"brand"}
                        value={manfId}
                        setValue={setValue}
                        Selected={brand || temp_data?.manfId}
                        marginImg={" 2.5px auto 2.5px auto"}
                        Imgheight={lessthan600 && "45.75px"}
                        ImgWidth={lessthan600 && "72.5%"}
                        height={
                          lessthan360 ? "75px" : lessthan600 ? "94px" : "88px"
                        }
                        width={
                          lessthan360 ? "124px" : lessthan600 ? "124px" : ""
                        }
                        fontSize={
                          lessthan360 ? "9.5px" : lessthan600 ? "10px" : ""
                        }
                        lessthan600={lessthan600}
                        fontWeight={lessthan600 && "800"}
                        lessthan360={lessthan360}
                        shadow={lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 20px"}
                        objectFit
                      />
                    </Col>
                  ))}
              </Row>
              {!_.isEmpty(OtherBrands) && (
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
                          ? "Show Popular Brands"
                          : `Show All ${OtherBrands?.length} Brands`
                        : "Don't See your Vehicle's Brand? Click Here"}
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
                    name="brand_other"
                    render={({ onChange, onBlur, value, name }) => (
                      <MultiSelect
                        name={name}
                        value={value}
                        onChange={onChange}
                        ref={register}
                        onBlur={onBlur}
                        isMulti={false}
                        options={Options}
                        placeholder={"Select Brand"}
                        errors={errors.brand_other}
                        Styled
                        closeOnSelect
                        onClick={(e) => onSubmit(e)}
                      />
                    )}
                  />
                  {!!errors?.brand_other && (
                    <Error className="mt-1">
                      {errors?.brand_other?.message}
                    </Error>
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
                    type={"button"}
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
              <SkeletonRow margin={"15px"} count={3} height={94} />
              <SkeletonRow count={3} height={94} />
              <SkeletonRow count={3} height={94} />
              <SkeletonRow count={3} height={94} />
            </>
          ) : (
            <>
              <SkeletonRow count={4} height={88} />
              <SkeletonRow count={4} height={88} />
              <SkeletonRow count={4} height={88} />
            </>
          )}
        </>
      )}
    </>
  );
};
