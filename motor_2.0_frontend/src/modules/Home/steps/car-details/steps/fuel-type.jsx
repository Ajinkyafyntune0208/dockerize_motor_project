/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect, useMemo } from "react";
import { Row, Col, Form } from "react-bootstrap";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import _ from "lodash";
import { Tile } from "components";
import swal from "sweetalert";
import { useDispatch, useSelector } from "react-redux";
import {
  set_temp_data,
  variant,
  SaveQuoteData,
  clear,
  getFuelType,
  fueldelay as fdelay,
  getFuel as setFuelType,
} from "modules/Home/home.slice";
import { useMediaPredicate } from "react-media-hook";
import { FuelType as FuelSources } from "../helper";
import { SkeletonRow } from "./skeleton";
import { _useMMVTracking } from "analytics/input-pages/mmv-tracking";
import { TypeReturn } from "modules/type";

export const FuelType = ({ stepFn, enquiry_id, token, type }) => {
  const dispatch = useDispatch();
  const { temp_data, loading, saveQuoteData, getFuel, fueldelay } = useSelector(
    (state) => state.home
  );
  const [btnDisable, setbtnDisable] = useState(false);

  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");

  //setLoader for fuel api & clear previous fuel type data
  useEffect(() => {
    dispatch(fdelay());
    dispatch(setFuelType([]));
    dispatch(clear("fuelCheck"));
  }, []);

  //get available fuel types
  useMemo(() => {
    if (temp_data?.productSubTypeId && temp_data?.modelId)
      dispatch(
        getFuelType({
          modelId: temp_data?.modelId,
          productSubTypeId: temp_data?.productSubTypeId,
          enquiryId: enquiry_id,
        })
      );
  }, [temp_data?.modelId]);

  const availableTypes = !_.isEmpty(getFuel)
    ? getFuel.map((item) => item.toUpperCase())
    : [];

  //Fuel Type List
  const Fuel = _.compact(FuelSources(availableTypes));

  // validation schema
  const yupValidate = yup.object({
    fuel: yup.string().required("Fuel type is required").nullable(),
  });

  const { handleSubmit, register, watch, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  const fuel = watch("fuel");

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      _useMMVTracking("fuel-type", temp_data?.fuel, TypeReturn(type));
      stepFn(3, 4);
    }
    return () => {
      dispatch(clear("saveQuoteData"));
    };
  }, [saveQuoteData]);

  useEffect(() => {
    if (fuel) {
      setbtnDisable(true);
      dispatch(
        set_temp_data({
          fuel: fuel,
          leadJourneyEnd: true,
          leadStageId: 2,
        })
      );
      //clearing previous variant data
      dispatch(variant([]));
      //save
      dispatch(
        SaveQuoteData({
          ...(token && { token: token }),
          stage: "6",
          fuelType: fuel,
          vehicleLpgCngKitValue: null,
          model: temp_data?.modelId,
          modelName: temp_data?.modelName,
          manfactureId: temp_data?.manfId,
          manfactureName: temp_data?.manfName,
          userProductJourneyId: enquiry_id,
          enquiryId: enquiry_id,
        })
      );
      setTimeout(() => setbtnDisable(false), 2500);
    }
  }, [fuel]);

  const onSubmit = (data) => {
    setbtnDisable(true);
    dispatch(
      set_temp_data({
        fuel: data?.fuel,
        kit_val: data?.kit_val ? data?.kit_val : null,
        leadJourneyEnd: true,
        leadStageId: 2,
      })
    );
    //clearing previous variant data
    dispatch(variant([]));
    //save
    dispatch(
      SaveQuoteData({
        ...(token && { token: token }),
        stage: "6",
        fuelType: data?.fuel,
        vehicleLpgCngKitValue: data?.kit_val ? data?.kit_val : null,
        model: temp_data?.modelId,
        modelName: temp_data?.modelName,
        manfactureId: temp_data?.manfId,
        manfactureName: temp_data?.manfName,
        userProductJourneyId: enquiry_id,
        enquiryId: enquiry_id,
      })
    );
    setTimeout(() => setbtnDisable(false), 2500);
  };

  useEffect(() => {
    if (errors?.fuel?.message) {
      swal(
        "Error",
        `${`Trace ID:- ${
          temp_data?.traceId ? temp_data?.traceId : enquiry_id
        }.\n Error Message:- ${errors?.fuel?.message}`}`,
        "error"
      );
    }
  }, [errors]);

  return (
    <>
      {!fueldelay && !btnDisable && !loading ? (
        <>
          <Form
            onSubmit={handleSubmit(onSubmit)}
            className={`w-100 d-flex flex-column align-content-center ElemFade ${
              lessthan600 ? "mt-4" : ""
            }`}
          >
            <Row
              className=" d-flex w-100 mx-auto"
              style={{ justifyContent: "space-evenly" }}
            >
              {Fuel.map((item, index) => (
                <Col
                  xs="12"
                  sm="12"
                  md="4"
                  lg="3"
                  xl="3"
                  className={`w-100 mx-auto ${!lessthan600 ? "d-flex" : ""}`}
                  style={{
                    ...(!lessthan600 && { justifyContent: "space-evenly" }),
                  }}
                >
                  <Tile
                    logo={item?.logo}
                    text={item?.name}
                    id={item?.id}
                    register={register}
                    name={"fuel"}
                    value={item?.value}
                    height={
                      lessthan360 ? "60px" : lessthan600 ? "75px" : "135px"
                    }
                    width={lessthan600 ? "100%" : "160px"}
                    imgMargin={lessthan600 ? "auto 2.5px auto 2.5px" : "10px"}
                    ImgWidth={lessthan360 ? "40px" : lessthan600 && "55px"}
                    setValue={setValue}
                    Selected={
                      fuel || temp_data?.fuel === "LPG"
                        ? "CNG"
                        : temp_data?.fuel
                    }
                    Imgheight={!lessthan600 && "70px"}
                    flatTile={lessthan600}
                    fontSize={lessthan360 ? "14px" : lessthan600 && "16px"}
                    fontWeight={lessthan600 && "800"}
                    flatTilexs={lessthan360}
                    shadow={lessthan600 && "rgb(0 0 0 / 20%) 0px 4px 20px"}
                    fuelType={
                      import.meta.env.VITE_BROKER === "UIB" ? true : false
                    }
                  />
                </Col>
              ))}
            </Row>
          </Form>
        </>
      ) : (
        <>
          {lessthan767 ? (
            <>
              <SkeletonRow margin={"25px"} count={1} height={75} />
              <SkeletonRow margin={"10px"} count={1} height={75} />
            </>
          ) : (
            <>
              <SkeletonRow count={2} width={160} height={135} />
            </>
          )}
        </>
      )}
    </>
  );
};
